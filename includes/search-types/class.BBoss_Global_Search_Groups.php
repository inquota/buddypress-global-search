<?php 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('BBoss_Global_Search_Groups')):

	/**
	 *
	 * BuddyPress Global Search  - search groups
	 * **************************************
	 *
	 *
	 */
	class BBoss_Global_Search_Groups extends BBoss_Global_Search_Type {
		private $type = 'groups';
		
		/**
		 * Insures that only one instance of Class exists in memory at any
		 * one time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0.0
		 *
		 * @return object BBoss_Global_Search_Groups
		 */
		public static function instance() {
			// Store the instance locally to avoid private static replication
			static $instance = null;

			// Only run these methods if they haven't been run previously
			if (null === $instance) {
				$instance = new BBoss_Global_Search_Groups();
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

		public function sql( $search_term, $only_totalrow_count=false ){
			/* an example UNION query :- 
			-----------------------------------------------------
			(
				SELECT 
					DISTINCT g.id, 'groups' as type, g.name LIKE '%ho%' AS relevance, gm2.meta_value as entry_date 
				FROM 
					wp_bp_groups_groupmeta gm1, wp_bp_groups_groupmeta gm2, wp_bp_groups g 
				WHERE 
					1=1 
					AND g.id = gm1.group_id 
					AND g.id = gm2.group_id 
					AND gm2.meta_key = 'last_activity' 
					AND gm1.meta_key = 'total_member_count' 
					AND ( g.name LIKE '%ho%' OR g.description LIKE '%ho%' )
			)
			----------------------------------------------------
			*/
			global $wpdb, $bp;
			$query_placeholder = array();
			
			$sql = " SELECT ";
			
			if( $only_totalrow_count ){
				$sql .= " COUNT( DISTINCT g.id ) ";
			} else {
				$sql .= " DISTINCT g.id, 'groups' as type, g.name LIKE '%%%s%%' AS relevance, gm2.meta_value as entry_date ";
				$query_placeholder[] = $search_term;
			}
						
			$sql .= " FROM 
						{$bp->groups->table_name_groupmeta} gm1, {$bp->groups->table_name_groupmeta} gm2, {$bp->groups->table_name} g 
					WHERE 
						1=1 
						AND g.id = gm1.group_id 
						AND g.id = gm2.group_id 
						AND gm2.meta_key = 'last_activity' 
						AND gm1.meta_key = 'total_member_count' 
						AND ( g.name LIKE '%%%s%%' OR g.description LIKE '%%%s%%' )
				";
			$query_placeholder[] = $search_term;
			$query_placeholder[] = $search_term;
			return $wpdb->prepare( $sql, $query_placeholder );
		}
		
		protected function generate_html( $template_type='' ){
			$group_ids = array();
			foreach( $this->search_results['items'] as $item_id=>$item_html ){
				$group_ids[] = $item_id;
			}

			//now we have all the posts
			//lets do a groups loop
			if( bp_has_groups( array( 'include'=>$group_ids, 'per_page'=>count($group_ids) ) ) ){
				while ( bp_groups() ){
					bp_the_group();

					$result = array(
						'id'	=> bp_get_group_id(),
						'type'	=> $this->type,
						'title'	=> bp_get_group_name(),
						'html'	=> buddyboss_global_search_buffer_template_part( 'loop/group', $template_type, false ),
					);

					$this->search_results['items'][bp_get_group_id()] = $result;
				}

				//var_dump( $this->search_results['items'] );
			}
		}
	}

// End class BBoss_Global_Search_Groups

endif;
?>