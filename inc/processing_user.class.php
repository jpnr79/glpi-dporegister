<?php
/*
 -------------------------------------------------------------------------
 DPO Register plugin for GLPI
 Copyright (C) 2018 by the DPO Register Development Team.

 https://github.com/karhel/glpi-dporegister
 -------------------------------------------------------------------------

 LICENSE

 This file is part of DPO Register.

    static function showForProcessing(\CommonGLPI $processing)
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 DPO Register is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with DPO Register. If not, see <http://www.gnu.org/licenses/>.

 --------------------------------------------------------------------------

  @package   dporegister
  @author    Karhel Tmarr
  @copyright Copyright (c) 2010-2013 Uninstall plugin team
  @license   GPLv3+
             http://www.gnu.org/licenses/gpl.txt
  @link      https://github.com/karhel/glpi-dporegister
  @since     2018
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginDporegisterProcessing_User extends PluginDporegisterCommonProcessingActor
{
    public static $itemtype_1 = null;
    public static $items_id_1 = null;
    public static $itemtype_2 = null;
    public static $items_id_2 = null;
    public static function init()
    {
        self::$itemtype_1 = PluginDporegisterProcessing::class;
        self::$items_id_1 = PluginDporegisterProcessing::getForeignKeyField();

        self::$itemtype_2 = User::class;
        self::$items_id_2 = User::getForeignKeyField();
    }

    // --------------------------------------------------------------------
    //  PLUGIN MANAGEMENT - DATABASE INITIALISATION
    // --------------------------------------------------------------------

    /**
     * Install or update PluginDporegisterProcessing_User
     *
     * @param Migration $migration Migration instance
     * @param string    $version   Plugin current version
     *
     * @return boolean
     */
    public static function install(Migration $migration, $version): bool
    {
        global $DB;
        $table = self::getTable();

        if (!$DB->tableExists($table)) {

            $query = "CREATE TABLE `$table` (
                `id` int(11) NOT NULL auto_increment,
                `" . self::$items_id_1 . "` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugins_dporegister_processings (id)',
                `" . self::$items_id_2 . "` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)', 
                `type` int(11) NOT NULL DEFAULT '1',
                `use_notification` tinyint(1) NOT NULL DEFAULT '1',
                `alternative_email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                
                PRIMARY KEY  (`id`),
                UNIQUE KEY `unicity` (`" . self::$items_id_1 . "`,`type`,`" . self::$items_id_2 . "`,`alternative_email`),
                KEY `user` (`" . self::$items_id_2 . "`,`type`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

            $DB->query($query) or die("error creating $table " . $DB->error());
        }

        // Migrate Joint Controller	to multiple type (User or Supplier)
        PluginDporegisterProcessing::checkUsersFields();
        return true;
    }

    /**
     * Uninstall PluginDporegisterProcessing_User
     *
     * @return boolean
     */
    public static function uninstall(): bool
    {
        global $DB;
        $table = self::getTable();

        if ($DB->tableExists($table)) {
            $query = "DROP TABLE `$table`";
            $DB->query($query) or die("error deleting $table " . $DB->error());
        }

        // Purge the logs table of the entries about the current class
        $query = "DELETE FROM `glpi_logs`
            WHERE `itemtype` = '" . __CLASS__ . "' 
            OR `itemtype_link` = '" . self::$itemtype_1 . "'";

        $DB->query($query) or die("error purge logs table");

        return true;
    }

    // --------------------------------------------------------------------
    //  GLPI PLUGIN COMMON
    // --------------------------------------------------------------------

    //! @copydoc CommonDBTM::canUpdate()
    function canCreateItem()
    {
        return PluginDporegisterProcessing::canUpdate();
    }

    //! @copydoc CommonDBTM::canUpdate()
    function canUpdateItem()
    {
        return $this->checkEntitiesValues() 
            && PluginDporegisterProcessing::canUpdate();
    }

    //! @copydoc CommonDBTM::canDelete()
    function canDeleteItem()
    {
        return $this->checkEntitiesValues() 
            && PluginDporegisterProcessing::canDelete();
    }

    //! @copydoc CommonDBTM::canPurge()
    function canPurgeItem()
    {
        return $this->checkEntitiesValues() 
            && PluginDporegisterProcessing::canPurge();
    }

    protected function checkEntitiesValues()
    {
        $entityId = $this->fields['entities_id'] ?? null;
        if (empty($entityId)) {
            return true;
        }

        $entity = new PluginDporegisterRepresentative();
        if (!$entity->getFromDBByCrit(['entities_id' => $entityId])) {
            return true;
        }

        $type = $this->fields['type'] ?? null;
        $usersId = $this->fields['users_id'] ?? null;

        if ($type == PluginDporegisterCommonProcessingActor::LEGAL_REPRESENTATIVE
            && $usersId == ($entity->fields['users_id_representative'] ?? null)) {
            return false;
        }

        if ($type == PluginDporegisterCommonProcessingActor::DPO
            && $usersId == ($entity->fields['users_id_dpo'] ?? null)) {
            return false;
        }

        return true;
    }

    static function getEntitiesValues($entityId)
    {
        if (empty($entityId)) {
            return [];
        }

        $entity = new PluginDporegisterRepresentative();
        if ($entity->getFromDBByCrit(['entities_id' => $entityId])) {
            return $entity->fields;
        }

        return [];
    }
}

// Emulate static constructor
PluginDporegisterProcessing_User::init();