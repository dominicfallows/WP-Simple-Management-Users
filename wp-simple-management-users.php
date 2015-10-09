<?php
  /*
    Plugin Name: WP Simple Management Users
    Plugin URI: https://github.com/dominicfallows/wp-simple-management-users
    GitHub Plugin URI: https://github.com/dominicfallows/wp-simple-management-users
    Description: Allow selected user types to become management users with the capabilities you choose
    Version: 0.1.0
    Author: Dominic Fallows
    Author URI: https://github.com/dominicfallows
    License: GPL2

    Copyright (c) 2015 Dominic Fallows (http://dominicfallows.uk)

    This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License, version 2, as published by the Free Software Foundation. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details. You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  */

  // Exit if accessed directly
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }

  /*** EDIT THESE OPTIONS ***/

  // Chose the user roles to become management users
  $smu_management_roles = array(
    'editor'
  );

  // Choose the management capabilities you would like your management users to have
  $smu_management_capabilities = array(
    'add_users',
    'create_users',
    'delete_users',
    'edit_users',
    'list_users',
    //'promote_users',
    'remove_users',
    'manage_downloads', // Added to enable use of Download Monitor (https://www.download-monitor.com) for our chosen management users
    'dlm_manage_logs' // Added to enable use of Download Monitor (https://www.download-monitor.com) for our chosen management users
  );

  // Chose the users roles that you would like your managemnet users to be able to manage
  $smu_user_roles_to_manage = array(
    'subscriber'
  );



  /*** NO NEED TO EDIT BELOW HERE ***/

  $smu = new Simple_Management_Users($smu_management_roles, $smu_management_capabilities, $smu_user_roles_to_manage);

  // 1. Add the capabilities to the required user roles - on plugin activation
  register_activation_hook( __FILE__, array( &$smu, 'smu_activation' ) );

  // 2. Remove the capabilities from the required user roles - on plugin deactivation
  register_deactivation_hook( __FILE__, array( &$smu, 'smu_deactivation' ) );

  // 3. Now let's handle the roles allowed for editing by the management users
  add_filter( 'editable_roles', array( &$smu, 'smu_filter_roles' ) );

  // 4. Prevent the management users from accessing the "admin" user.php and user-edit.php pages
  add_action( 'admin_init', array( &$smu, 'smu_stop_access_admin_profile' ) );

  // 5. Prevent management users from editing Administrator users in SQL queries
  add_action('pre_user_query', array( &$smu, 'smu_pre_user_query' ) );

  // 6. Add custom CSS to admin
  add_action('admin_head', array( &$smu, 'smu_styles' ) );


  class Simple_Management_Users {

    private $_smu_management_roles;
    private $_smu_management_capabilities;
    private $_smu_user_roles_to_manage;

    public function Simple_Management_Users($roles, $capabilities, $users) {
      $this->_smu_management_roles = $roles;
      $this->_smu_management_capabilities = $capabilities;
      $this->_smu_user_roles_to_manage = $users;
    }

  	// 1. Add the capabilities to the required user roles - on plugin activation
  	public function smu_activation() {
      foreach( $this->_smu_management_roles as $r ) {
        $role = get_role( $r );
        if( $role ) {
          foreach($this->_smu_management_capabilities as $capability) {
            $role->add_cap( $capability );
          }
        }
      }
  	}

  	// 2. Remove the capabilities from the required user roles - on plugin deactivation
  	public function smu_deactivation() {
      foreach( $this->_smu_management_roles as $r ) {
        $role = get_role( $r );
        if( $role ) {
          foreach($this->_smu_management_capabilities as $capability) {
            $role->remove_cap( $capability );
          }
        }
      }
  	}


  	// 3. Now let's handle the roles allowed for editing by the management users
  	public function smu_filter_roles( $roles ) {
      $user = wp_get_current_user();

      if ( !empty(array_intersect($this->_smu_management_roles, $user->roles)) ) {
        $tmp = array_keys( $roles );
        foreach( $tmp as $r ) {
        	if(in_array($r, $this->_smu_user_roles_to_manage)) {
          	continue;
          }
          unset( $roles[$r] );
        }
      }
      return $roles;
  	}

  	// 4. Prevent the management users from accessing the "admin" user.php and user-edit.php pages
  	public function smu_stop_access_admin_profile() {

      global $pagenow;
      if (isset($_REQUEST['user_id'])) {
        $user_id = $_REQUEST['user_id'];
      } else if (isset($_REQUEST['user'])) {
        $user_id = $_REQUEST['user'];
      } else {
        $user_id = 0;
      }
      $level = get_user_meta($user_id, 'wp_user_level', true) ;
      $user = wp_get_current_user();

      $blocked_admin_pages = array('user-edit.php', 'users.php');
      if ( !empty(array_intersect($this->_smu_management_roles, $user->roles)) ) {
        if( in_array($pagenow, $blocked_admin_pages) && ($level == 10) ) { // 10 corresponds to admin level.
          wp_die( 'You cannot access the admin user.' );
        }
      }
  	}

    // 5. Prevent management users from editing Administrator users in SQL queries
    public function smu_pre_user_query($user_search) {
      $user = wp_get_current_user();
      if (!current_user_can('administrator')) { // Current user does not have an admistrator role
        global $wpdb;

        // Remove users with administrator capabilites from the user_search
        $user_search->query_where =
        str_replace('WHERE 1=1',
            "WHERE 1=1 AND {$wpdb->users}.ID IN (
                 SELECT {$wpdb->usermeta}.user_id FROM $wpdb->usermeta
                    WHERE {$wpdb->usermeta}.meta_key = '{$wpdb->prefix}capabilities'
                    AND {$wpdb->usermeta}.meta_value NOT LIKE '%administrator%')",
            $user_search->query_where
        );
      }
    }

    // 6. Add custom CSS to admin
    public function smu_styles() {
      $user = wp_get_current_user();
      if (!current_user_can('administrator')) { // Current user does not have an admistrator role
        echo '<style type="text/css">input[type=checkbox].administrator { visibility: hidden; }</style>';
      }
    }

  }



?>
