<?php

class Character extends MX_Controller
{
    private $canCache;

    private $js;
    private $css;
    private $id;
    private $realm;
    private $realmName;

    private $class;
    private $className;
    private $race;
    private $raceName;
    private $level;
    private $account;
    private $gender;

    private $stats;
    private $slotsItems;
    private $equippedItems;
    private $equippedItemsDisplayId;

    public function __construct()
    {
        parent::__construct();

        requirePermission("view");

        // Set JS and CSS paths
        $this->js = "modules/character/js/character.js";
        $this->css = "modules/character/css/character.css";

        $this->load->model("armory_model");

        $this->canCache = true;
        $this->slotsItems = array();
        $this->equippedItems = array();
        $this->equippedItemsDisplayId = array();
    }

    /**
     * Initialize
     */
    public function index($realm = false, $id = false)
    {
        if (!is_numeric($id)) {
            $id = ucfirst($id);
            $id = $this->realms->getRealm($realm)->getCharacters()->getGuidByName($id);
        }

        $this->setId($realm, $id);

        if ($this->realms->getRealm($realm)->getCharacters()->characterExists($id)) { //characterExists
            $this->getProfile();
        } else {
            $this->getError();
        }
    }

    public function getItem($id = false)
    {
        if ($id) {
            $cache = $this->cache->get("items/item_" . $this->realm . "_" . $id);

            if ($cache !== false) {
                return "<a href='" . $this->template->page_url . "item/" . $this->realm . "/" . $id . "' rel='item=" . $id . "' data-realm='" . $this->realm . "'></a><img src='https://icons.wowdb.com/retail/large/" . ($cache['icon'] != null ? $cache['icon'] : 'inv_misc_questionmark') . ".jpg' />";
            } else {
                $this->canCache = false;
                return $this->template->loadPage("icon_ajax.tpl", array('id' => $id, 'realm' => $this->realm, 'url' => $this->template->page_url));
            }
        }
    }

    /**
     * Determinate which Id to assign
     */
    public function setId($realm, $id)
    {
        // Check if valid X-Y format
        if (
            is_numeric($realm)
            && is_numeric($id)
        ) {
            $this->realm = $realm;
            $this->id = $id;

            $this->armory_model->setRealm($this->realm);
            $this->armory_model->setId($this->id);
        } else {
            $this->realm = false;
            $this->id = false;
        }
    }

    /**
     * Get character info
     */
    private function getInfo()
    {
        $character_data = $this->armory_model->getCharacter();

        if ($this->realms->getRealm($this->realm)->getEmulator()->hasStats()) {
            $character_stats = $this->armory_model->getStats();
        } else {
            $character_stats = array('maxhealth' => lang("unknown", "character"));
        }

        $this->pvp = array(
            'totalKills' => (array_key_exists("totalKills", $character_data)) ? $character_data['totalKills'] : false,
            'todayKills' => (array_key_exists("todayKills", $character_data)) ? $character_data['todayKills'] : false,
            'yesterdayKills' => (array_key_exists("yesterdayKills", $character_data)) ? $character_data['yesterdayKills'] : false,
            'honor' => (array_key_exists("totalHonorPoints", $character_data)) ? $character_data['totalHonorPoints'] : false,
            'arena' => (array_key_exists("arenaPoints", $character_data)) ? $character_data['arenaPoints'] : false
        );

        // Assign the character data as real variables
        foreach ($character_data as $key => $value) {
            $this->$key = $value;
        }

        // Assign the character stats
        $this->stats = $character_stats;

        // Get the account username
        $this->accountName = $this->internal_user_model->getNickname($this->account);

        $this->guild = $this->armory_model->getGuild();
        $this->guildName = $this->armory_model->getGuildName($this->guild);

        if (in_array($this->race, array(4, 10))) {
            if ($this->race == 4) {
                $this->raceName = "Night elf";
            } else {
                $this->raceName = "Blood elf";
            }
        } else {
            $this->raceName = $this->armory_model->realms->getRaceEN($this->race);
        }

        $this->className = $this->armory_model->realms->getClassEN($this->class);
        $this->realmName = $this->armory_model->realm->getName();

        if ($this->realms->getRealm($this->realm)->getEmulator()->hasStats()) {
            // Find out which power field to use
            switch ($this->className) {
                default:
                    if (isset($this->stats['maxpower4'])) {
                        $this->secondBar = "Mana";
                        $this->secondBarValue = $this->stats['maxpower1'];
                    } else {
                        $this->secondBar = "Mana";
                        $this->secondBarValue = "Unknown";
                    }
                    break;

                case "Warrior":
                    if (isset($this->stats['maxpower4'])) {
                        $this->secondBar = "Rage";
                        $this->secondBarValue = $this->stats['maxpower2'] / 10;
                    } else {
                        $this->secondBar = "Rage";
                        $this->secondBarValue = "Unknown";
                    }
                    break;

                case "Rogue":
                    if (isset($this->stats['maxpower4'])) {
                        $this->secondBar = "Energy";
                        $this->secondBarValue = $this->stats['maxpower4'];
                    } else {
                        $this->secondBar = "Energy";
                        $this->secondBarValue = "Unknown";
                    }
                    break;

                case "Hunter":
                    if ($this->stats['maxpower3']) {
                        $this->secondBar = "Focus";
                        $this->secondBarValue = $this->stats['maxpower3'];
                    } else {
                        $this->secondBar = "Mana";
                        $this->secondBarValue = $this->stats['maxpower1'];
                    }
                    break;
                case "Death knight":
                    if (isset($this->stats['maxpower7'])) {
                        $this->secondBar = "Runic";
                        $this->secondBarValue = $this->stats['maxpower7'] / 10;
                    } else {
                        $this->secondBar = "Runic";
                        $this->secondBarValue = "Unknown";
                    }
                    break;
                case "Demon Hunter":
                    if ($this->stats['maxpower2']) {
                        $this->secondBar = "Fury";
                        $this->secondBarValue = $this->stats['maxpower2'];
                    } else {
                        $this->secondBar = "Fury";
                        $this->secondBarValue = "Unknown";
                    }
                    break;
                case "Monk":
                    if ($this->realms->getRealm($this->realm)->getExpansionId() > 8) {
                        if ($this->stats['maxpower2']) {
                            $this->secondBar = "Energy";
                            $this->secondBarValue = $this->stats['maxpower2'];
                        } else {
                            $this->secondBar = "Mana";
                            $this->secondBarValue = $this->stats['maxpower1'];
                        }
                    } else {
                        if ($this->stats['maxpower4']) {
                            $this->secondBar = "Energy";
                            $this->secondBarValue = $this->stats['maxpower4'];
                        } else {
                            $this->secondBar = "Mana";
                            $this->secondBarValue = $this->stats['maxpower1'];
                        }
                    }
                    break;
            }
        } else {
            $this->secondBar = "mana";
            $this->secondBarValue = lang("unknown", "character");
        }

        // Load the items
        $slotsItems = $this->armory_model->getItems();

        // Item slots
        $slots = array(
            0 => "head",
            1 => "neck",
            2 => "shoulders",
            3 => "body",
            4 => "chest",
            5 => "waist",
            6 => "legs",
            7 => "feet",
            8 => "wrists",
            9 => "hands",
            10 => "finger1",
            11 => "finger2",
            12 => "trinket1",
            13 => "trinket2",
            14 => "back",
            15 => "mainhand",
            16 => "offhand",
            17 => "ranged",
            18 => "tabard"
        );

        if (is_array($slotsItems)) {
            // Loop through to assign the items
            foreach ($slotsItems as $item) {
                $this->equippedItems[$item['slot']] = $item['itemEntry'];
                $this->slotsItems[$slots[$item['slot']]] = $this->getItem($item['itemEntry']);
                $this->getDisplayId($item['slot'], $item['itemEntry']);
            }
        }

        // Loop through to make sure none are empty
        foreach ($slots as $key => $value) {
            if (!array_key_exists($value, $this->slotsItems)) {
                switch ($value) {
                    default:
                        $image = $value;
                        break;
                    case "trinket2":
                    case "trinket1":
                        $image = "trinket";
                        break;
                    case "finger2":
                    case "finger1":
                        $image = "finger";
                        break;
                    case "back":
                        $image = "chest";
                        break;
                }

                $this->slotsItems[$value] = "<div class='item'><img src='" . $this->template->page_url . "application/images/armory/default/" . $image . ".gif' /></div>";
            }
        }
    }

    private function getBackground(): string
    {
        if ($this->className == "Demon Hunter") {
            return "mardum";
        }
        switch ($this->raceName) {
            default:
                return "shattrath";
            case "Human":
                return "stormwind";
            case "Blood elf":
                return "silvermoon";
            case "Night elf":
                return "darnassus";
            case "Gnome":
            case "Dwarf":
                return "ironforge";
            case "Troll":
            case "Orc":
                return "orgrimmar";
            case "Draenei":
                return "theexodar";
            case "Tauren":
                return "thunderbluff";
            case "Undead":
                return "undercity";
            case "Goblin":
                return "kezan";
            case "Worgen":
                return "gilneas";
            case "Pandaren":
                return "wanderingisle";
            case "Nightborne":
                return "nightwell";
            case "Highmountain Tauren":
                return "highmountain";
            case "Void elf":
                return "telogrusrift";
            case "Lightforged Dranei":
                return "vindicaar";
            case "Zandalari Troll":
                return "zandalari";
            case "Kul Tiran":
                return "boralus";
            case "Dark Iron Dwarf":
                return "shadowforge";
            case "Vulpera":
                return "voldun";
            case "Mag'har Orc":
                return "orgrimmar2";
            case "Mechagnome":
                return "mechagon";
            case "Dracthyr":
                return "wakingshores";
        }
    }

    /**
     * Load the profile
     *
     * @return String
     */
    private function getProfile()
    {
        $cache = $this->cache->get("character_" . $this->realm . "_" . $this->id . "_" . getLang());

        if ($cache !== false) {
            $this->template->setTitle($cache['name']);
            $this->template->setDescription($cache['description']);
            $this->template->setKeywords($cache['keywords']);

            $page = $cache['page'];
        } else {
            if ($this->armory_model->characterExists()) {
                // Load all items and info
                $this->getInfo();

                $this->template->setTitle($this->name);

                $avatarArray = array(
                    'class' => $this->class,
                    'race' => $this->race,
                    'level' => $this->level,
                    'gender' => $this->gender
                );

                $charData = array(
                    "name" => $this->name,
                    "race" => $this->race,
                    "class" => $this->class,
                    "level" => $this->level,
                    "gender" => $this->gender,
                    "items" => $this->slotsItems,
                    "equippedItems" => (!empty($this->equippedItems) ? $this->equippedItems : false),
                    "equippedItemsDisplayId" => (!empty($this->equippedItemsDisplayId) ? $this->equippedItemsDisplayId : false),
                    "expansionId" => $this->realms->getRealm($this->realm)->getExpansionId(),
                    "guild" => $this->guild,
                    "guildName" => $this->guildName,
                    "pvp" => $this->pvp,
                    "url" => $this->template->page_url,
                    "raceName" => $this->raceName,
                    "className" => $this->className,
                    "realmName" => $this->realmName,
                    "avatar" => $this->armory_model->realms->formatAvatarPath($avatarArray),
                    "stats" => $this->stats,
                    "secondBar" => $this->secondBar,
                    "secondBarValue" => $this->secondBarValue,
                    "bg" => $this->getBackground(),
                    "realmId" => $this->realm,
                    "fcms_tooltip" => $this->config->item("use_fcms_tooltip"),
                    "has_stats" => $this->realms->getRealm($this->realm)->getEmulator()->hasStats(),
                    "faction" => $this->realms->getRealm($this->realm)->getCharacters()->getFaction($this->id)
                );

                $character = $this->template->loadPage("character.tpl", $charData);

                $data = array(
                    "module" => "default",
                    "headline" => "<span style='cursor:pointer;' data-tip='" . lang("view_profile", "character") . "' onClick='window.location=\"" . $this->template->page_url . "profile/" . $this->account . "\"'>" . $this->name,
                    "content" => $character
                );

                $keywords = "armory," . $charData['name'] . ",lv" . $charData['level'] . "," . $charData['raceName'] . "," . $charData['className'] . "," . $charData['realmName'];
                $description = $charData['name'] . " - level " . $charData['level'] . " " . $charData['raceName'] . " " . $charData['className'] . " on " . $charData['realmName'];

                $this->template->setDescription($description);
                $this->template->setKeywords($keywords);

                $page = $this->template->loadPage("page.tpl", $data);
            } else {
                $keywords = "";
                $description = "";

                $page = $this->getError(true);
            }

            if ($this->canCache) {
                // Cache for 30 min
                $this->cache->save("character_" . $this->realm . "_" . $this->id . "_" . getLang(), array('page' => $page, 'name' => $this->name, 'keywords' => $keywords, 'description' => $description), 60 * 30);
            }
        }

        $this->template->view($page, $this->css, $this->js);
    }

    /**
     * Show "character doesn't exist" error
     */
    private function getError($get = false)
    {
        $this->template->setTitle(lang("doesnt_exist", "character"));

        $data = array(
            "module" => "default",
            "headline" => lang("doesnt_exist", "character"),
            "content" => "<center style='margin:10px;font-weight:bold;'>" . lang("doesnt_exist_long", "character") . "</center>"
        );

        $page = $this->template->loadPage("page.tpl", $data);

        if ($get) {
            return $page;
        } else {
            $this->template->view($page);
        }
    }

    public function getDisplayId($slot, $id)
    {
        // Check if item ID
        if ($id) {
            // get item data
            $item_in_cache = $this->items->getItem($id, $this->realm, 'displayid');

            if ($item_in_cache) {
                $displayId = $item_in_cache;
            } else {
                $displayId = null;
            }
        }

        if ($displayId == null || $displayId == '')
            return;

        switch ($slot) {
            case 0:
                $this->equippedItemsDisplayId[1] = $displayId;
                break;
            case 2:
                $this->equippedItemsDisplayId[3] = $displayId;
                break;
            case 3:
                $this->equippedItemsDisplayId[4] = $displayId;
                break;
            case 4:
                $this->equippedItemsDisplayId[5] = $displayId;
                break;
            case 5:
                $this->equippedItemsDisplayId[6] = $displayId;
                break;
            case 6:
                $this->equippedItemsDisplayId[7] = $displayId;
                break;
            case 7:
                $this->equippedItemsDisplayId[8] = $displayId;
                break;
            case 8:
                $this->equippedItemsDisplayId[9] = $displayId;
                break;
            case 9:
                $this->equippedItemsDisplayId[10] = $displayId;
                break;
            case 14:
                $this->equippedItemsDisplayId[16] = $displayId;
                break;
            case 15:
                $this->equippedItemsDisplayId[21] = $displayId;
                break;
            case 16:
                $this->equippedItemsDisplayId[14] = $displayId;
                break;
            case 18:
                $this->equippedItemsDisplayId[19] = $displayId;
                break;
        }
    }
}
