<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * @package FusionCMS
 * @author  Jesper Lindström
 * @author  Xavier Geerinck
 * @author  Elliott Robbins
 * @author  Keramat Jokar (Nightprince) <https://github.com/Nightprince>
 * @author  Ehsan Zare (Darksider) <darksider.legend@gmail.com>
 * @link    https://github.com/FusionWowCMS/FusionCMS
 */

class User
{
    private $CI;

    // User details
    private int $id;
    private string $username;
    private string $password;
    private mixed $email;
    private int $expansion;
    private ?bool $online;
    private mixed $vp;
    private mixed $dp;
    private ?string $register_date;
    private ?string $last_ip;
    private ?string $nickname;
    private ?string $totp_secret;

    public function __construct()
    {
        //Get the instance of the CI
        $this->CI = &get_instance();

        //Set the default user values;
        $this->getUserData();
    }

    /**
     * When they log in, this should be called to set all the user details.
     *
     * @param String $username
     * @param String $sha_pass_hash
     * @return Int
     */
    public function setUserDetails(string $username, string $sha_pass_hash): int
    {
        $check = $this->CI->external_account_model->initialize($username);

        if (!$check) {
            return 1;
        } elseif (strtoupper($this->CI->external_account_model->getShaPassHash()) == strtoupper($sha_pass_hash)) {
            // Load the internal values (vp, dp etc.)
            $this->CI->internal_user_model->initialize($this->CI->external_account_model->getId());

            $userdata = array(
                'uid' => $this->CI->external_account_model->getId(),
                'username' => $this->CI->external_account_model->getUsername(),
                'password' => $this->CI->external_account_model->getShaPassHash(),
                'email' => $this->CI->external_account_model->getEmail(),
                'expansion' => $this->CI->external_account_model->getExpansion(),
                'online' => true,
                'register_date' => preg_replace("/\s.*/", "", $this->CI->external_account_model->getJoinDate()),
                'last_ip' => $this->CI->external_account_model->getLastIp(),
                'nickname' => $this->CI->internal_user_model->getNickname(),
                'language' => $this->CI->internal_user_model->getLanguage(),
            );

            // Set the session with the above data
            $this->CI->session->set_userdata($userdata);

            // Reload this object.
            $this->getUserData();

            return 0;
        } else {
            //Return an error
            return 2;
        }
    }

    /**
     * Creates a hash of the password we enter
     *
     * @param String $username
     * @param String $password in plain text
     * @return string|array hashed password
     */
    public function createHash(string $username = "", string $password = ""): string|array
    {
        return $this->CI->realms->getEmulator()->encrypt($username, $password);
    }

    /**
     * Creates a hash of the password we enter
     * @param String $email
     * @param String $password in plain text
     * @return String hashed password
     */
    public function createHash2(string $email = "", string $password = ""): string
    {
        return $this->CI->realms->getEmulator()->encrypt2($email, $password);
    }

    /**
     * Check if the user rank has any staff permissions
     *
     * @param int|bool $id
     * @return     Boolean
     * @deprecated 6.1
     */
    public function isStaff(int|bool $id = false): bool
    {
        $id = $id ? $id : $this->id;
        return ($this->isGm($id) || $this->isDev($id) || $this->isAdmin($id) || $this->isOwner($id));
    }

    /**
     * Check if the user has the mod permission
     * Uses [view, mod] ACL permission as of 6.1, for backwards compatibility
     *
     * @param int|bool $id
     * @return     Boolean
     * @deprecated 6.1
     */
    public function isGm(int|bool $id = false): bool
    {
        $id = $id ? $id : $this->id;
        return hasPermission("view", "gm", $id);
    }

    /**
     * Check if the user has the developer permission
     * Uses [view, mod] ACL permission as of 6.1, for backwards compatibility
     *
     * @param int|bool $id
     * @return     Boolean
     * @deprecated 6.1
     */
    public function isDev(int|bool $id = false): bool
    {
        $id = $id ? $id : $this->id;
        return hasPermission("view", "gm", $id);
    }

    /**
     * Check if the user has the admin permission
     * Uses [view, admin] ACL permission as of 6.1, for backwards compatibility
     *
     * @param int|bool $id
     * @return     Boolean
     * @deprecated 6.1
     */
    public function isAdmin(int|bool $id = false): bool
    {
        $id = $id ? $id : $this->id;
        return hasPermission("view", "admin", $id);
    }

    /**
     * Check if the user has the owner permission
     * Uses [view, admin] ACL permission as of 6.1, for backwards compatibility
     *
     * @param int|bool $id
     * @return     Boolean
     * @deprecated 6.1
     */
    public function isOwner(int|bool $id = false): bool
    {
        $id = $id ? $id : $this->id;
        return hasPermission("view", "admin", $id);
    }

    /**
     * Require the user to be signed in to proceed
     */
    public function userArea(): void
    {
        //A check, so it requires you to be logged in.
        if (!$this->online) {
            $this->CI->template->view($this->CI->template->loadPage("page.tpl", array(
                "module" => "default",
                "headline" => lang("denied"),
                "content" => "<center style='margin:10px;font-weight:bold;'>" . lang("must_be_signed_in") . "</center>"
            )));
        }

        return;
    }

    /**
     * Require the user to be signed out to proceed
     */
    public function guestArea(): void
    {
        //A check, so it requires you to be logged out.
        if ($this->online) {
            $this->CI->template->view($this->CI->template->loadPage("page.tpl", array(
                "module" => "default",
                "headline" => lang("denied"),
                "content" => "<center style='margin:10px;font-weight:bold;'>" . lang("already_signed_in") . "</center>"
            )));
        }

        return;
    }

    /**
     * Please see userArea() instead
     *
     * @deprecated 6.05
     */
    public function is_logged_in(): void
    {
        $this->userArea();
    }

    /**
     * Please see guestArea() instead
     *
     * @deprecated 6.05
     */
    public function is_not_logged_in(): void
    {
        $this->guestArea();
    }

    /**
     * Whether the user is online or not
     *
     * @return Boolean
     */
    public function isOnline(): bool
    {
        return $this->online;
    }

    /**
     * Check if rank A is bigger than rank B
     * Necessary to compare number-based ranks
     * with "az" and "a" ranks in ArcEmu & AscEmu.
     *
     * @param  Mixed $a
     * @param  Mixed $b
     * @return Boolean
     */
    private function rankBiggerThan(mixed $a, mixed $b): bool
    {
        $a = ($a == "") ? 0 : $a;
        $b = ($b == "") ? 0 : $b;

        if ($a === $b) {
            return false;
        }

        // Return true if b is bigger than a
        if (is_numeric($a) && is_numeric($b)) {
            if ($a < $b) {
                return true;
            } else {
                return false;
            }
        } elseif (!is_numeric($a) && !is_numeric($b) && in_array($a, array("az", "a")) && in_array($b, array("az", "a"))) {
            switch ($a) {
                case "az":
                    $a = 1;
                    break;
                case "a":
                    $a = 0;
                    break;
            }

            switch ($b) {
                case "az":
                    $b = 1;
                    break;
                case "a":
                    $b = 0;
                    break;
            }

            if ($a < $b) {
                return true;
            } else {
                return false;
            }
        } elseif (in_array($a, array("az", "a")) && is_numeric($b)) {
            return false;
        } else {
            // Unknown
            return true;
        }
    }

    /*
    | -------------------------------------------------------------------
    |  Getters
    | -------------------------------------------------------------------
    */

    public function getUserData(): void
    {
        // If they are logged in sync the settings with our object
        if ($this->CI->session->userdata('online')) {
            $this->id = $this->CI->session->userdata('uid');
            $this->username = $this->CI->session->userdata('username');
            $this->password = $this->CI->session->userdata('password');
            $this->email = $this->CI->session->userdata('email');
            $this->expansion = $this->CI->session->userdata('expansion');
            $this->online = true;
            $this->register_date = $this->CI->session->userdata('register_date');
            $this->last_ip = $this->CI->session->userdata('last_ip');
            $this->totp_secret = $this->CI->session->userdata('totp_secret');
            $this->nickname = $this->CI->session->userdata('nickname');
            $this->vp = false;
            $this->dp = false;
        } else {
            $this->id = 0;
            $this->username =  0;
            $this->password = 0;
            $this->email = null;
            $this->expansion = 0;
            $this->online = false;
            $this->vp = 0;
            $this->dp = 0;
            $this->register_date = null;
            $this->last_ip = null;
            $this->totp_secret = null;
            $this->nickname = null;

        }

        $this->CI->language->setLanguage($this->CI->session->userdata('language') ? $this->CI->session->userdata('language') : $this->CI->config->item('language'));

        // Load acl
        //$this->CI->load->library('acl');
        //$this->CI->acl->initialize($this->id);
    }

    /**
     * Check if the account is banned or active
     *
     * @param bool $id
     * @return String
     */
    public function getAccountStatus(bool $id = false): string
    {
        if (!$id) {
            $id = $this->id;
        }

        $result = $this->CI->external_account_model->getBannedStatus($id);

        if (!$result) {
            return 'Active';
        } else {
            if (array_key_exists("banreason", $result)) {
                return '<span style="color:red;cursor:pointer;" data-tip="<b>' . lang("reason") . '</b> ' . $result['banreason'] . '">' . lang("banned") . ' (?)</span>';
            } else {
                return '<span style="color:red;">' . ucfirst(lang("banned")) . '</span>';
            }
        }
    }

    /**
     * Get the nickname
     *
     * @param false|Int $id
     * @return string|null
     */
    public function getNickname(false|int $id = false): string | null
    {
        return $this->CI->internal_user_model->getNickname($id);
    }

    /**
     * Get the user's avatar
     * @param false|Int $id
     * @return string
     */
    public function getAvatar(false|int $id = false): string
    {
        return base_url().APPPATH . "images/avatar/". $this->CI->internal_user_model->getAvatar($id);
    }

    /**
     * Get the user's avatar id
     */
    public function getAvatarId($id = false)
    {
        return $this->CI->internal_user_model->getAvatarId($id);
    }

    /**
     * get the user it's characters, returns array with realmnames and character names and character id when specified realm is -1 or the default
     *
     * @param int $userId
     * @param int $realmId
     * @return false|array
     */
    public function getCharacters(int $userId, int $realmId = -1): false|array
    {
        if ($realmId && $userId) {
            $out = array(); //Init the return param

            if ($realmId == -1) { //Get all characters
                //to Get the realms
                $realms = $this->CI->realms->getRealms();

                foreach ($realms as $realm) {
                    //Init the vars of the databases
                    $character = $realm->getCharacters();

                    //Open the connection to the databases
                    $character->connect();

                    //Execute queries on it by getting the connection
                    $characters = $character->getCharactersByAccount($this->id);

                    $character_data = array('realmId' => $realm->getId(),'realmName' => $realm->getName(), 'characters' => $characters);

                    $out[] = $character_data;
                }

                return $out;
            } else { //Get the characters for the specified realm
                $realm = $this->realms->getRealm($realmId);

                $character = $realm->getCharacters();

                //Open the connection to the databases
                $character->connect();

                //Execute queries on it by getting the connection
                $characters = $character->getCharactersByAccount($this->id);

                return array('realmId' => $realm->getId(),'realmName' => $realm->getName(), 'characters' => $characters);
            }
        } else {
            return false;
        }
    }

    /**
     * Get the userId from the current User or the given Username
     *
     * @param bool|string $username
     * @return int
     */
    public function getId(string|bool $username = false): int
    {
        if (!$username) {
            return $this->id;
        } else {
            return $this->CI->external_account_model->getId($username);
        }
    }

    /**
     * Get the username of the current user or the given id.
     *
     * @param int|bool $id
     * @return String
     */
    public function getUsername(int|bool $id = false): string
    {
        return $this->CI->external_account_model->getUsername($id);
    }

    /**
     * Get the password of the user
     *
     * @return String
     */
    public function getPassword(): string
    {
        $this->getUserData();
        return $this->password;
    }

    /**
     * Get the email of the user
     *
     * @return mixed
     */
    public function getEmail(): mixed
    {
        return $this->email;
    }

    /**
     * Get the expansion of the user
     *
     * @return int
     */
    public function getExpansion(): int
    {
        $this->getUserData();
        return $this->expansion;
    }

    /**
     * Get if the user is online
     *
     * @return boolean
     */
    public function getOnline(): bool
    {
        return $this->online;
    }

    /**
     * Get the register date
     *
     * @return string|null
     */
    public function getRegisterDate(): string|null
    {
        return $this->register_date;
    }

    /**
     * Get the number of vp
     *
     * @return int
     */
    public function getVp(): int
    {
        if ($this->vp === false) {
            $this->vp = $this->CI->internal_user_model->getVp();
        }

        return $this->vp;
    }

    /**
     * Get the number of dp
     *
     * @return int
     */
    public function getDp(): int
    {
        if ($this->dp === false) {
            $this->dp = $this->CI->internal_user_model->getDp();
        }

        return $this->dp;
    }

    /**
     * Get the last ip
     *
     * @return string|null
     */
    public function getLastIP(): string | null
    {
        return $this->last_ip;
    }

    /**
     * Get the Totp secret
     *
     * @return string|null
     */
    public function getTotpSecret(): string | null
    {
        return $this->totp_secret;
    }

    /**
     * Set the Totp secret
     *
     * @param string $secret
     * @param int $userId
     * @return void
     */
    public function setTotpSecret(string $secret, int $userId = 0): void
    {
        if ($userId)
            $this->CI->realms->getEmulator()->setTotp($userId, $secret);

        $this->CI->session->set_userdata('totp_secret', $secret);
    }

    /*
    | -------------------------------------------------------------------
    |  Setters
    | -------------------------------------------------------------------
    */

    /**
     * Set the username of the user.
     *
     * @param $newUsername
     */
    public function setUsername($newUsername): void
    {
        if (!$newUsername) {
            return;
        }
        $this->CI->external_account_model->setUsername($this->username, $newUsername);
        $this->CI->session->set_userdata('username', $newUsername);
    }

    /**
     * Set the language of the user
     *
     * @param $newLanguage
     */
    public function setLanguage($newLanguage): void
    {
        if (!$newLanguage) {
            return;
        }
        $this->CI->internal_user_model->setLanguage($this->id, $newLanguage);
        $this->CI->session->set_userdata('language', $newLanguage);
    }

    /**
     * Set the password of the user
     *
     * @param $newPassword
     */
    public function setPassword($newPassword): void
    {
        if (!$newPassword) {
            return;
        }
        $this->CI->external_account_model->setPassword($this->username, $newPassword);
        $this->CI->session->set_userdata('password', $newPassword);
    }

    /**
     * Set the email of the user
     *
     * @param $newEmail
     */
    public function setEmail($newEmail): void
    {
        if (!$newEmail) {
            return;
        }
        $this->CI->external_account_model->setEmail($this->username, $newEmail);
        $this->CI->session->set_userdata('email', $newEmail);
    }

    /**
     * Set the expansion of the user
     *
     * @param $newExpansion
     */
    public function setExpansion($newExpansion): void
    {
        $this->CI->external_account_model->setExpansion($newExpansion, $this->username);
        $this->CI->session->set_userdata('expansion', $newExpansion);
    }

    /**
     * Set the amount of vp for the user
     *
     * @param $newVp
     */
    public function setVp($newVp): void
    {
        $this->vp = $newVp;
        $this->CI->internal_user_model->setVp($this->id, $newVp);
    }

    /**
     * Set the amount of dp for the user
     *
     * @param $newDp
     */
    public function setDp($newDp): void
    {
        $this->dp = $newDp;
        $this->CI->internal_user_model->setDp($this->id, $newDp);
    }

    /**
     * Set the role id of the user
     *
     * @param $newRoleId
     */
    public function setRoleId($newRoleId): void
    {
        $this->role = $newRoleId;
        $this->CI->internal_user_model->setRoleId($this->id, $newRoleId);
    }

    /**
	 * Set the avatar id of the user
	 * @param $newAvatarId
	 */
	public function setAvatar($newAvatarId): void
    {
		$this->avatarId = $newAvatarId;
		$this->CI->internal_user_model->setAvatar($this->id, $newAvatarId);
	}
}
