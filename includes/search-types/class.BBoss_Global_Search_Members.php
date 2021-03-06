<?php 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('BBoss_Global_Search_Members')):

	/**
	 *
	 * BuddyPress Global Search  - search members
	 * **************************************
	 *
	 *
	 */
	class BBoss_Global_Search_Members extends BBoss_Global_Search_Type {
		private $type = 'members';
		
		/**
		 * Insures that only one instance of Class exists in memory at any
		 * one time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0.0
		 *
		 * @return object BBoss_Global_Search_Members
		 */
		public static function instance() {
			// Store the instance locally to avoid private static replication
			static $instance = null;

			// Only run these methods if they haven't been run previously
			if (null === $instance) {
				$instance = new BBoss_Global_Search_Members();
				
				add_action( 'bboss_global_search_settings_item_members', array( $instance, 'print_search_options' ) );
			}

			// Always return the instance
			return $instance;
		}
		
		/**
		 * A dummy constructor to prevent this class from being loaded more than once.
		 *
		 * @since 1.0.0
		 */
		private function __construct() { /* Do nothing here */
		}
		
		/**
		 * Generates sql for members search.
		 * 
		 * @todo: if Mr.X has set privacy of xprofile field 'location' data as 'private'
		 * then, location of Mr.X shouldn't be checked in searched.
		 * 
		 * @since 1.0.0
		 * @param string $search_term
		 * @param boolean $only_totalrow_count
		 * 
		 * @return string sql query
		 */
		public function sql( $search_term, $only_totalrow_count=false ){
			global $wpdb, $bp;
			$query_placeholder = array(); 
			$items_to_search = buddyboss_global_search()->option('items-to-search');
			
			$COLUMNS = " SELECT ";
			
			if( $only_totalrow_count ){
				$COLUMNS .= " COUNT( DISTINCT u.id ) ";
			} else {
				$COLUMNS .= " DISTINCT u.id, 'members' as type, u.display_name LIKE '%%%s%%' AS relevance, a.date_recorded as entry_date ";
				$query_placeholder[] = $search_term;
			}
			
			$FROM = " {$wpdb->users} u JOIN {$bp->members->table_name_last_activity} a ON a.user_id=u.id ";
			
			$WHERE = array();
			$WHERE[] = "1=1";
			$where_fields = array();
			
			/* ++++++++++++++++++++++++++++++++
			 * wp_users table fields
			 +++++++++++++++++++++++++++++++ */
			$user_fields = array();
			foreach( $items_to_search as $item ){
				//should start with member_field_ prefix
				//see print_search_options
				if( strpos( $item, 'member_field_' )===0 ){
					$user_fields[] = str_replace( 'member_field_', '', $item );
				}
			}
			
			if( !empty( $user_fields ) ){
				$conditions_wp_user_table = array();
				foreach ( $user_fields as $user_field ){
					$conditions_wp_user_table[] = $user_field . " LIKE '%%%s%%' ";
					$query_placeholder[] = $search_term;
				}
				
				$clause_wp_user_table = "u.id IN ( SELECT ID FROM {$wpdb->users}  WHERE ( ";
				$clause_wp_user_table .= implode( ' OR ', $conditions_wp_user_table );
				$clause_wp_user_table .= " ) ) ";
				
				$where_fields[] = $clause_wp_user_table;
			}
			/* _____________________________ */
			
			/* ++++++++++++++++++++++++++++++++
			 * xprofile fields
			 +++++++++++++++++++++++++++++++ */
			//get all selected xprofile fields
			if( function_exists( 'bp_is_active' ) && bp_is_active( 'xprofile' ) ){
				$groups = bp_xprofile_get_groups( array(
					'fetch_fields' => true
				) );

				if ( !empty( $groups ) ){
					$selected_xprofile_fields = array();
					foreach ( $groups as $group ){
						if ( !empty( $group->fields ) ){
							foreach ( $group->fields as $field ) {
								$item = 'xprofile_field_' . $field->id;
								if( !empty( $items_to_search ) && in_array( $item, $items_to_search ) )
									$selected_xprofile_fields[] = $field->id;
							}
						}
					}
					
					if( !empty( $selected_xprofile_fields ) ){
						//u.id IN ( SELECT user_id FROM {$bp->profile->table_name_data} WHERE value LIKE '%%%s%%' )
						$clause_xprofile_table = "u.id IN ( SELECT user_id FROM {$bp->profile->table_name_data} WHERE value LIKE '%%%s%%' AND field_id IN ( ";
						$clause_xprofile_table .= implode( ',', $selected_xprofile_fields );
						$clause_xprofile_table .= " ) ) ";

						$where_fields[] = $clause_xprofile_table;
						$query_placeholder[] = $search_term;
					}
				}
			}
			/* _____________________________ */
			
			if( !empty( $where_fields ) )
				$WHERE[] = implode ( ' OR ', $where_fields );
			
			// other conditions
			$WHERE[] = " a.component = 'members' ";
			$WHERE[] = " a.type = 'last_activity' ";
			
			$sql = $COLUMNS . ' FROM ' . $FROM . ' WHERE ' . implode( ' AND ', $WHERE );
			if( !$only_totalrow_count ){
				$sql .= " GROUP BY u.id ";
			}
			
			$sql = $wpdb->prepare( $sql, $query_placeholder );
            
            return apply_filters( 
                'BBoss_Global_Search_Members_sql', 
                $sql, 
                array( 
                    'search_term'           => $search_term,
                    'only_totalrow_count'   => $only_totalrow_count,
                ) 
            );
		}
		
		protected function generate_html( $template_type='' ){
			$group_ids = array();
			foreach( $this->search_results['items'] as $item_id => $item ){
				$group_ids[] = $item_id;
			}

			//now we have all the posts
			//lets do a groups loop
			if( bp_has_members( array( 'include'=>$group_ids, 'per_page'=>count($group_ids) ) ) ){
				while ( bp_members() ){
					bp_the_member();

					$result_item = array(
						'id'	=> bp_get_member_user_id(),
						'type'	=> $this->type,
						'title'	=> bp_get_member_name(),
						'html'	=> buddyboss_global_search_buffer_template_part( 'loop/member', $template_type, false ),
					);

					$this->search_results['items'][bp_get_member_user_id()] = $result_item;
				}
			}
		}
		
		/**
		 * What fields members should be searched on?
		 * Prints options to search through username, email, nicename/displayname.
		 * Prints xprofile fields, if xprofile component is active.
		 * 
		 * @since 1.1.0
		 */
		function print_search_options( $items_to_search ){
			echo "<div class='wp-user-fields' style='margin: 10px 0 0 30px'>";
			echo "<p class='xprofile-group-name' style='margin: 5px 0'><strong>" . __('Account','buddypress-global-search') . "</strong></p>";

			$fields = array(
				'user_login'	=> __( 'Username/Login', 'buddyboss-global-search' ),
				'display_name'	=> __( 'Display Name', 'buddyboss-global-search' ),
				'user_email'	=> __( 'Email', 'buddyboss-global-search' ),
			);
			foreach( $fields as $field=>$label ){
				$item = 'member_field_' . $field;
				$checked = !empty( $items_to_search ) && in_array( $item, $items_to_search ) ? ' checked' : '';
				echo "<label><input type='checkbox' value='{$item}' name='buddyboss_global_search_plugin_options[items-to-search][]' {$checked}>{$label}</label><br>";
			}
			
			echo "</div><!-- .wp-user-fields -->";
			
			if( !function_exists( 'bp_is_active' ) || !bp_is_active( 'xprofile' ) )
				return;
			
			$groups = bp_xprofile_get_groups( array(
				'fetch_fields' => true
			) );
			
			if ( !empty( $groups ) ){
				echo "<div class='xprofile-fields' style='margin: 0 0 10px 30px'>";
				foreach ( $groups as $group ){
					echo "<p class='xprofile-group-name' style='margin: 5px 0'><strong>" . $group->name . "</strong></p>";
					 
					if ( !empty( $group->fields ) ){
						foreach ( $group->fields as $field ) {
							//lets save these as xprofile_field_{field_id}
							$item = 'xprofile_field_' . $field->id;
							$checked = !empty( $items_to_search ) && in_array( $item, $items_to_search ) ? ' checked' : '';
							echo "<label><input type='checkbox' value='{$item}' name='buddyboss_global_search_plugin_options[items-to-search][]' {$checked}>{$field->name}</label><br>";
						}
					}
				}
				echo "</div><!-- .xprofile-fields -->";
			}
		}
	}

// End class BBoss_Global_Search_Members

endif;
?>