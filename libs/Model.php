<?php
/**
 * This file is a part of Xen Orchesrta.
 *
 * Xen Orchestra is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Xen Orchestra is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Xen Orchestra. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Xen Orchestra
 * @license http://www.gnu.org/licenses/gpl-3.0-standalone.html GPLv3
 **/

final class Model
{
	public static function create_user($name, $password, $email, $permission,
		$pw_hashed = false)
	{
		if (!Database::is_enabled())
		{
			return false;
		}

		if (!$pw_hashed)
		{
			$password = md5($password);
		}

		$id = Database::get_instance()->insert_user($name, $password, $email,
			ACL::to_string($permission));

		if ($id === false)
		{
			return false;
		}
		return self::$users[$name] = new User($id, $name, $password, $email,
			$permission);
	}

	public static function delete_user($name)
	{
		if (Database::is_enabled() && Database::get_instance()->delete_user($name))
		{
			unset(self::$users[$name]); // He may have been in the cache.
			return true;
		}
		return false;
	}

	/**
	 * Returns the current user.
	 *
	 * If the user is not registered or if the database is disabled, the
	 * returned user is "guest".
	 *
	 * @return The current user.
	 */
	public static function get_current_user()
	{
		if (self::$current_user !== null)
		{
			return self::$current_user;
		}

		if (isset($_SESSION['user']))
		{
			self::$current_user = self::get_user($_SESSION['user']);

			if (self::$current_user !== false)
			{
				// The user has been successfully retrieved.
				return self::$current_user;
			}

			// An error occured, unregisters the user and falls back to "guest".
			self::unregister_current_user();
		}
		return self::$current_user = self::get_user('guest');
	}

	/**
	 * Returns the dom0 which has the id $id in the database or null.
	 *
	 * @param string  $id           The identifier of the dom0.
	 * @param boolean $ignore_cache The results are cached, pass true to ignore
	 *                              it.
	 *
	 * @return The dom0 if present, otherwise false.
	 */
	public static function get_dom0($id, $ignore_cache = false)
	{
		if ($ignore_cache)
		{
			$config = Config::get_instance();
			if (isset($config->$id))
			{
				$entries = $config->$id;

				list($address, $port) = explode (':', $id, 2);
				try
				{
					return self::$dom0s[$id] = new Dom0(
						$address,
						$port,
						isset($entries['username']) ? $entries['username'] : 'none',
						isset($entries['password']) ? $entries['password'] : 'none'
					);
				}
				catch (Exception $e)
				{
						return false;
				}
			}
			// There is no such dom0.
			return self::$dom0s[$id] = false; // It may have existed.
		}

		if (isset(self::$dom0s[$id]))
		{
			return (self::$dom0s[$id]);
		}
		if (self::$all_dom0s_retrieved)
		{
			return false;
		}

		// Not found but may exist, recall this method with $ignore_cache set
		// to true.
		return self::get_dom0($id, true);
	}

	/**
	 * Returns all the dom0s present in the database.
	 *
	 * @param boolean $ignore_cache The results are cached, pass true to ignore
	 *                              it.
	 *
	 * @return An array containing all the dom0 (can be empty).
	 */
	public static function get_dom0s($ignore_cache = false)
	{
		if ($ignore_cache || !self::$all_dom0s_retrieved)
		{
			$config = Config::get_instance();
			$dom0s = array(); // Necessary for the force refresh.
			self::$all_dom0s_retrieved = true;
			foreach ($config as $entry => $entries)
			{
				// Checks if this entry is a dom0.
				if (is_array($entries) && (strpos($entry, ':') !== false))
				{
					list($address, $port) = explode (':', $entry, 2);
					try
					{
						self::$dom0s[$entry] = new Dom0(
							$address,
							$port,
							isset($entries['username']) ? $entries['username'] : 'none',
							isset($entries['password']) ? $entries['password'] : 'none'
						);
					}
					catch (Exception $e)
					{
						// so far just put error in $error var
						// TODO : use error code to display on web page
						$error = 'ERROR: '.$address.' is not reachable.';
						//echo $error;
						//self::$dom0s[$entry] = $error;
					}
				}
			}
		}
		return self::$dom0s;
	}

	/**
	 * Returns a reference to an array containing all the domUs of a dom0.
	 */
	public static function &get_domUs(Dom0 $dom0)
	{
		self::$domUs_by_dom0s[$dom0->id] = array();
		$xids = $dom0->rpc_query('VM.get_all');
		foreach ($xids as $xid)
		{
			// The domU Domain-0 is special, do not insert
			// it in the domUs array.
			if ($xid === '00000000-0000-0000-0000-000000000000')
			{
				continue;
			}

			$domU = new DomU($xid, $dom0);

			if (($domU->power_state === 'Halted')
				&& self::is_running_domU_named($domU->name))
			{
				continue;
			}

			if (($domU->power_state === 'Running') || ($domU->power_state === 'Paused'))
			{
				if (isset(self::$domUs_by_names[$domU->name]))
				{
					foreach (self::$domUs_by_names[$domU->name] as $dom0_id => $domU_)
					{
						if ($domU_->power_state === 'Halted')
						{
							unset (self::$domUs_by_dom0s[$dom0_id][$domU->id]);
							unset (self::$domUs_by_names[$domU->name][$dom0_id]);
						}
					}
				}
			}

			self::$domUs_by_dom0s[$dom0->id][$domU->id] = $domU;
			if (!isset(self::$domUs_by_names[$domU->name]))
			{
				self::$domUs_by_names[$domU->name] = array($dom0->id => $domU);
			}
			else
			{
				self::$domUs_by_names[$domU->name][$dom0->id] = $domU;
			}
		}

		return self::$domUs_by_dom0s[$dom0->id];
	}

	/**
	 * Returns the user who has the name $name if he exists, otherwise returns
	 * false.
	 *
	 * If $password is not null, the user's password will also be checked, if
	 * not correct, the function will return false.
	 *
	 * @param string      $name     The user's name.
	 * @param string|null $password The user's password.
	 *
	 * @return The user or false.
	 */
	public static function get_user($name, $password = null, $pw_hashed = false)
	{
		if (!isset(self::$users[$name]))
		{
			if (self::$all_users_retrieved)
			{
				// The user is not in the cache and we know we have all the
				// users in it, so we know he does not exist.
				return false;
			}
			if (Database::is_enabled())
			{
				self::$users[$name] = Database::get_instance()->get_user('name',
					$name);

				if (self::$users[$name] === false)
				{
					if ($name !== 'guest')
					{
						return false;
					}
					// There must be a "guest" user in the database.
					self::create_user('guest', '', '', ACL::NONE);
				}
			}
			else
			{
				if ($name !== 'guest')
				{
					// The database is disabled, only "guest" is available.
					return false;
				}
				self::$users['guest'] = self::get_default_guest();
			}
		}
		if (self::$users[$name] === false) // Already checked, not here.
		{
			return false;
		}
		if ($password !== null)
		{
			if (!$pw_hashed)
			{
				$password = md5($password);
			}
			if ($password !== self::$users[$name]->password)
			{
				return false;
			}
		}
		return self::$users[$name];
	}

	public static function get_users($ignore_cache = false)
	{
		if (self::$all_users_retrieved && !$ignore_cache)
		{
			return self::$users;
		}

		self::$all_users_retrieved = true;

		if (!Database::is_enabled())
		{
			return self::$users = array(
				'guest' => self::get_default_guest()
			);
		}

		self::$users = Database::get_instance()->get_users();
		if (!isset(self::$users['guest'])) // "guest" must always exist.
		{
			// There must be a "guest" user in the database.
			self::create_user('guest', '', '', ACL::NONE);
		}
		return self::$users;
	}

	public static function get_user_acls(User $user)
	{
		if (!Database::is_enabled())
		{
			return array();
		}

		$db = Database::get_instance();
		$stmt = $db->prepare('SELECT dom0_id, domU_name, permission '
			. 'FROM acls WHERE user_id = :user_id');

		if (!$stmt->execute(array(':user_id' => $user->id)))
		{
			return array(); // The request failed.
		}

		$acls = array();
		while (($r = $stmt->fetch(PDO::FETCH_NUM)) !== false)
		{
			$r[0] = rtrim($r[0]);
			if (!isset($acls[$r[0]]))
			{
				$acls[$r[0]] = array();
			}

			if ($r[1] === null) // For the whole dom0.
			{
				$acls[$r[0]]['Domain-0'] = $r[2];
			}
			else
			{
				$acls[$r[0]][rtrim($r[1])] = ACL::from_string($r[2]);
			}
		}
		return $acls;
	}

	/**
	 * Registers the current user.
	 * If an user named $name with the password $password exists, this user is
	 * registered as the current user, which means that each action will be done
	 * with his permissions.
	 *
	 * @param string      $name
	 * @param string|null $password
	 * @param bool        $pw_hashed
	 *
	 * @return The user if the registration was a success, otherwise false.
	 */
	public static function register_current_user($name, $password = null,
		$pw_hashed = false)
	{
		$user = self::get_user($name, $password, $pw_hashed);
		if ($user === false)
		{
			return false;
		}
		$_SESSION['user'] = $name;
		return self::$current_user = $user;
	}

	/**
	 * Unregisters the current user.
	 */
	public static function unregister_current_user()
	{
		self::$current_user = null;
		unset($_SESSION['user']);
	}

	/**
	 * Updates the database to match the given user, registers him if necessary.
	 *
	 * @param User $u The user to update.
	 *
	 * @return Whether the update was successful.
	 */
	public static function update_user(User $u)
	{
		return (Database::is_enabled() && Database::get_instance()->update_user($u));
	}

	/**
	 * The current user.
	 *
	 * @var User
	 */
	private static $current_user = null;

	/**
	 * To avoid unecessary checking and object creation, all dom0s are stored in
	 * this array.
	 *
	 * @var array
	 */
	private static $dom0s = array();

	/**
	 * This flag equals true if all the dom0s are already retrieved, otherwise
	 * it equals false.
	 *
	 * @var boolean
	 */
	private static $all_dom0s_retrieved = false;

	/**
	 * This array contains all the domUs: dom0_ids => domU_id => domU.
	 *
	 * @var array
	 */
	private static $domUs_by_dom0s = array();

	/**
	 * This array contains all the domUs:  names => dom0_id => domU.
	 *
	 * @var array
	 */
	private static $domUs_by_names = array();

	/**
	 * This array contains all the users already fetched from the database.
	 *
	 * @var array
	 */
	private static $users = array();


	/**
	 * This flag equals true if all the users are already retrieved, otherwise
	 * it equals false.
	 *
	 * @var boolean
	 */
	private static $all_users_retrieved = false;

	/**
	 * Returns the default "guest" user.
	 * The default "guest" user is the user "guest" when the database is
	 * disabled.
	 *
	 * @param $permission
	 *
	 * @return The default "guest" user.
	 */
	private static function get_default_guest($permission = null)
	{
		if ($permission === null)
		{
			$cfg = Config::get_instance();
			if (isset($cfg->global['default_guest_permission']))
			{
				$permission = ACL::from_string($cfg->global['default_guest_permission']);
			}
			else
			{
				$permission = ACL::NONE;
			}
		}
		return new User(-1, 'guest', '', '', $permission);
	}

	/**
	 * Checks if there is a running domU with the name $name among the known
	 * domUs.
	 *
	 * @param string name
	 *
	 * @return True if there is at least one, otherwise false.
	 */
	private static function is_running_domU_named($name)
	{
		if (isset(self::$domUs_by_names[$name]))
		{
			foreach (self::$domUs_by_names[$name] as $domU)
			{
				if ($domU->power_state === 'Running')
				{
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * This class cannot be instanciated.
	 */
	private function __construct()
	{}
}
