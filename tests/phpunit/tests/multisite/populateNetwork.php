<?php

if ( is_multisite() ) :

	/**
	 * Tests for populate_network functionality.
	 *
	 * @group ms-network
	 * @group multisite
	 */
	class Tests_Multisite_Populate_Network extends WP_UnitTestCase {
		protected $network_args;
		protected static $site_user_id;

		public function set_up() {
			parent::set_up();
			$this->network_args = array(
				'network_id'        => 2,
				'domain'            => 'example.org',
				'email'             => 'admin@example.org',
				'site_name'         => 'Test Network',
				'path'              => '/',
				'subdomain_install' => false,
			);
		}

		/**
		 * Tests the before_populate_network action.
		 *
		 * @ticket 27289
		 */
		public function test_before_populate_network_action() {
			$fired = 0;
			add_action(
				'before_populate_network',
				function ( $args ) use ( &$fired ) {
					$fired++;
					$this->assertSame( $this->network_args, $args );
				}
			);

			populate_network(
				$this->network_args['network_id'],
				$this->network_args['domain'],
				$this->network_args['email'],
				$this->network_args['site_name'],
				$this->network_args['path'],
				$this->network_args['subdomain_install']
			);

			$this->assertSame( 1, $fired );
		}

		/**
		 * Tests the after_populate_network action.
		 *
		 * @ticket 27289
		 */
		public function test_after_populate_network_action() {
			$fired = 0;
			add_action(
				'after_populate_network',
				function ( $args, $user_id ) use ( &$fired ) {
					$fired++;
					$this->assertSame( $this->network_args, $args );
					$this->assertIsInt( $user_id );
				},
				10,
				2
			);

			populate_network(
				$this->network_args['network_id'],
				$this->network_args['domain'],
				$this->network_args['email'],
				$this->network_args['site_name'],
				$this->network_args['path'],
				$this->network_args['subdomain_install']
			);

			$this->assertSame( 1, $fired );
		}

		/**
		 * Tests the after_upgrade_to_multisite action.
		 *
		 * @ticket 27289
		 */
		public function test_after_upgrade_to_multisite_action() {
			$fired = 0;
			add_action(
				'after_upgrade_to_multisite',
				function ( $args, $user_id ) use ( &$fired ) {
					$fired++;
					$this->assertSame( $this->network_args, $args );
					$this->assertIsInt( $user_id );
				},
				10,
				2
			);

			$this->network_args['network_id'] = 1;
			populate_network(
				$this->network_args['network_id'],
				$this->network_args['domain'],
				$this->network_args['email'],
				$this->network_args['site_name'],
				$this->network_args['path'],
				$this->network_args['subdomain_install']
			);

			$this->assertSame( 1, $fired );
		}
	}

endif;
