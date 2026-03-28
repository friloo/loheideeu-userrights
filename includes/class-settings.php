<?php
/**
 * Admin-Einstellungsseite: Menüpunkte pro Rolle konfigurieren.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wp_userrights_save', array( $this, 'save_settings' ) );
	}

	public function register_settings_page() {
		add_menu_page(
			__( 'Benutzerrechte', 'wp-userrights' ),
			__( 'Benutzerrechte', 'wp-userrights' ),
			'manage_options',
			'wp-userrights',
			array( $this, 'render_page' ),
			'dashicons-shield',
			80
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wp-userrights' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wp-userrights-admin',
			WP_USERRIGHTS_URL . 'assets/admin.css',
			array(),
			WP_USERRIGHTS_VERSION
		);
		wp_enqueue_script(
			'wp-userrights-admin',
			WP_USERRIGHTS_URL . 'assets/admin.js',
			array( 'jquery' ),
			WP_USERRIGHTS_VERSION,
			true
		);
	}

	/**
	 * Liest alle aktuell registrierten Menüpunkte aus den globalen Arrays.
	 * Gibt strukturiertes Array zurück: [ ['slug' => '...', 'label' => '...', 'children' => [...]], ... ]
	 */
	public function get_menu_tree() {
		global $menu, $submenu;

		$tree = array();

		if ( empty( $menu ) ) {
			return $tree;
		}

		ksort( $menu );

		foreach ( $menu as $item ) {
			$slug  = $item[2];
			$label = wp_strip_all_tags( $item[0] );

			// Separatoren überspringen
			if ( strpos( $slug, 'separator' ) === 0 || empty( $label ) ) {
				continue;
			}

			$node = array(
				'slug'     => $slug,
				'label'    => $label ?: $slug,
				'cap'      => isset( $item[1] ) ? $item[1] : 'read',
				'children' => array(),
			);

			if ( isset( $submenu[ $slug ] ) ) {
				foreach ( $submenu[ $slug ] as $sub ) {
					$sub_slug  = $sub[2];
					$sub_label = wp_strip_all_tags( $sub[0] );

					if ( empty( $sub_label ) ) {
						$sub_label = $sub_slug;
					}

					$node['children'][] = array(
						'slug'  => $sub_slug,
						'label' => $sub_label,
						'cap'   => isset( $sub[1] ) ? $sub[1] : 'read',
					);
				}
			}

			$tree[] = $node;
		}

		return $tree;
	}

	/**
	 * Gibt alle WordPress-Rollen außer administrator zurück.
	 */
	private function get_editable_roles() {
		$all_roles = wp_roles()->roles;
		unset( $all_roles['administrator'] );
		return $all_roles;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$roles       = $this->get_editable_roles();
		$permissions = get_option( WP_USERRIGHTS_OPTION, array() );
		$menu_tree   = $this->get_menu_tree();

		// Aktive Rolle aus GET-Parameter oder erste verfügbare
		$selected_role = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
		if ( empty( $selected_role ) || ! isset( $roles[ $selected_role ] ) ) {
			$selected_role = ! empty( $roles ) ? array_key_first( $roles ) : '';
		}

		$role_perms         = isset( $permissions[ $selected_role ] ) ? $permissions[ $selected_role ] : array();
		$allowed_slugs      = isset( $role_perms['menu_slugs'] ) ? (array) $role_perms['menu_slugs'] : array();
		$allowed_categories = isset( $role_perms['allowed_categories'] ) ? implode( ', ', (array) $role_perms['allowed_categories'] ) : '';
		$allowed_pages      = isset( $role_perms['allowed_page_slugs'] ) ? implode( ', ', (array) $role_perms['allowed_page_slugs'] ) : '';
		?>
		<div class="wrap wp-userrights-wrap">
			<h1><?php esc_html_e( 'Benutzerrechte', 'wp-userrights' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Wählen Sie eine Rolle und legen Sie fest, welche Backend-Menüpunkte diese Rolle sehen darf. Administratoren sehen immer alles.', 'wp-userrights' ); ?>
			</p>

			<?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Einstellungen gespeichert.', 'wp-userrights' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $roles ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Keine bearbeitbaren Rollen gefunden (außer Administrator).', 'wp-userrights' ); ?></p>
				</div>
			<?php else : ?>

			<!-- Rollenauswahl -->
			<div class="wp-userrights-role-selector">
				<label for="wp-userrights-role-select"><strong><?php esc_html_e( 'Rolle:', 'wp-userrights' ); ?></strong></label>
				<select id="wp-userrights-role-select">
					<?php foreach ( $roles as $role_key => $role_data ) : ?>
						<option value="<?php echo esc_attr( $role_key ); ?>"
							data-url="<?php echo esc_attr( add_query_arg( array( 'page' => 'wp-userrights', 'role' => $role_key ), admin_url( 'admin.php' ) ) ); ?>"
							<?php selected( $selected_role, $role_key ); ?>>
							<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Formular für gewählte Rolle -->
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wp-userrights-form">
				<?php wp_nonce_field( 'wp_userrights_save_' . $selected_role, 'wp_userrights_nonce' ); ?>
				<input type="hidden" name="action" value="wp_userrights_save">
				<input type="hidden" name="wp_userrights_role" value="<?php echo esc_attr( $selected_role ); ?>">

				<div class="wp-userrights-section">
					<h2>
						<?php
						$role_label = isset( $roles[ $selected_role ]['name'] ) ? translate_user_role( $roles[ $selected_role ]['name'] ) : $selected_role;
						/* translators: %s: role name */
						printf( esc_html__( 'Menürechte für Rolle: %s', 'wp-userrights' ), '<strong>' . esc_html( $role_label ) . '</strong>' );
						?>
					</h2>

					<div class="wp-userrights-toolbar">
						<button type="button" class="button" id="wp-userrights-check-all">
							<?php esc_html_e( 'Alle auswählen', 'wp-userrights' ); ?>
						</button>
						<button type="button" class="button" id="wp-userrights-uncheck-all">
							<?php esc_html_e( 'Alle abwählen', 'wp-userrights' ); ?>
						</button>
					</div>

					<?php if ( empty( $menu_tree ) ) : ?>
						<p class="description"><?php esc_html_e( 'Keine Menüpunkte gefunden. Das kann passieren, wenn die Seite noch im Aufbau ist.', 'wp-userrights' ); ?></p>
					<?php else : ?>
					<div class="wp-userrights-menu-tree">
						<?php foreach ( $menu_tree as $top_item ) : ?>
							<div class="menu-item-top">
								<?php // Capability dieses Top-Level-Eintrags für automatische Capability-Zuweisung ?>
								<input type="hidden"
									name="menu_cap_map[<?php echo esc_attr( $top_item['slug'] ); ?>]"
									value="<?php echo esc_attr( $top_item['cap'] ); ?>">
								<label class="menu-item-label top-level">
									<input type="checkbox"
										name="menu_slugs[]"
										value="<?php echo esc_attr( $top_item['slug'] ); ?>"
										class="menu-checkbox top-level-checkbox"
										<?php checked( in_array( $top_item['slug'], $allowed_slugs, true ) ); ?>>
									<span class="dashicons dashicons-menu-alt"></span>
									<?php echo esc_html( $top_item['label'] ); ?>
									<code class="slug-hint"><?php echo esc_html( $top_item['slug'] ); ?></code>
								</label>

								<?php if ( ! empty( $top_item['children'] ) ) : ?>
								<div class="submenu-items">
									<?php foreach ( $top_item['children'] as $child ) : ?>
									<?php // Eltern-Slug und Capability für dieses Kind (Auto-Include + Capability-Sync) ?>
									<input type="hidden"
										name="menu_parent_map[<?php echo esc_attr( $child['slug'] ); ?>]"
										value="<?php echo esc_attr( $top_item['slug'] ); ?>">
									<input type="hidden"
										name="menu_cap_map[<?php echo esc_attr( $child['slug'] ); ?>]"
										value="<?php echo esc_attr( $child['cap'] ); ?>">
									<label class="menu-item-label sub-level">
										<input type="checkbox"
											name="menu_slugs[]"
											value="<?php echo esc_attr( $child['slug'] ); ?>"
											class="menu-checkbox sub-level-checkbox"
											data-parent="<?php echo esc_attr( $top_item['slug'] ); ?>"
											<?php checked( in_array( $child['slug'], $allowed_slugs, true ) ); ?>>
										<span class="dashicons dashicons-arrow-right-alt2"></span>
										<?php echo esc_html( $child['label'] ); ?>
										<code class="slug-hint"><?php echo esc_html( $child['slug'] ); ?></code>
									</label>
									<?php endforeach; ?>
								</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>

				<!-- Inhaltsfilter -->
				<div class="wp-userrights-section">
					<h2><?php esc_html_e( 'Inhaltsfilter', 'wp-userrights' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Optional: Schränken Sie ein, welche Beiträge und Seiten diese Rolle in der Admin-Liste sehen darf. Leer lassen = alle sichtbar (sofern Menüzugriff besteht).', 'wp-userrights' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="wp-userrights-categories">
									<?php esc_html_e( 'Nur Beiträge in Kategorien', 'wp-userrights' ); ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="wp-userrights-categories"
									name="allowed_categories"
									value="<?php echo esc_attr( $allowed_categories ); ?>"
									class="regular-text"
									placeholder="mav, aktuell">
								<p class="description"><?php esc_html_e( 'Kategorie-Slugs, kommagetrennt. Leer = alle Kategorien.', 'wp-userrights' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wp-userrights-pages">
									<?php esc_html_e( 'Nur Seiten mit diesen Slugs', 'wp-userrights' ); ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="wp-userrights-pages"
									name="allowed_page_slugs"
									value="<?php echo esc_attr( $allowed_pages ); ?>"
									class="regular-text"
									placeholder="mav, kuenstlerteam">
								<p class="description"><?php esc_html_e( 'Seiten-Slugs, kommagetrennt. Leer = alle Seiten.', 'wp-userrights' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Einstellungen speichern', 'wp-userrights' ); ?>
					</button>
				</p>
			</form>

			<?php endif; ?>
		</div>
		<?php
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'wp-userrights' ) );
		}

		$role = isset( $_POST['wp_userrights_role'] ) ? sanitize_key( $_POST['wp_userrights_role'] ) : '';

		if ( empty( $role ) ) {
			wp_die( esc_html__( 'Ungültige Rolle.', 'wp-userrights' ) );
		}

		if ( ! wp_verify_nonce(
			isset( $_POST['wp_userrights_nonce'] ) ? $_POST['wp_userrights_nonce'] : '',
			'wp_userrights_save_' . $role
		) ) {
			wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'wp-userrights' ) );
		}

		$roles = wp_roles()->roles;
		if ( ! isset( $roles[ $role ] ) || 'administrator' === $role ) {
			wp_die( esc_html__( 'Ungültige Rolle.', 'wp-userrights' ) );
		}

		// Erlaubte Menü-Slugs
		$raw_slugs   = isset( $_POST['menu_slugs'] ) ? (array) $_POST['menu_slugs'] : array();
		$clean_slugs = array_values( array_filter( array_map( 'sanitize_text_field', $raw_slugs ) ) );

		// Punkt 2: Eltern-Slug automatisch miteinschließen wenn ein Kind erlaubt ist.
		// Das parent_map kommt als hidden input aus dem Formular: menu_parent_map[child] = parent
		$parent_map = isset( $_POST['menu_parent_map'] ) ? (array) $_POST['menu_parent_map'] : array();
		foreach ( $clean_slugs as $slug ) {
			if ( isset( $parent_map[ $slug ] ) ) {
				$parent_slug = sanitize_text_field( $parent_map[ $slug ] );
				if ( $parent_slug && ! in_array( $parent_slug, $clean_slugs, true ) ) {
					$clean_slugs[] = $parent_slug;
				}
			}
		}

		// Kategorie-Filter
		$raw_cats   = isset( $_POST['allowed_categories'] ) ? sanitize_text_field( $_POST['allowed_categories'] ) : '';
		$clean_cats = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $raw_cats ) ) ) );

		// Seiten-Slug-Filter
		$raw_pages   = isset( $_POST['allowed_page_slugs'] ) ? sanitize_text_field( $_POST['allowed_page_slugs'] ) : '';
		$clean_pages = array_values( array_filter( array_map( 'sanitize_title', explode( ',', $raw_pages ) ) ) );

		// Punkt 1: Capability-Map aus dem Formular lesen und Capabilities der Rolle synchronisieren
		$raw_cap_map   = isset( $_POST['menu_cap_map'] ) ? (array) $_POST['menu_cap_map'] : array();
		$clean_cap_map = array();
		foreach ( $raw_cap_map as $s => $c ) {
			$clean_cap_map[ sanitize_text_field( $s ) ] = sanitize_key( $c );
		}
		$managed_caps = $this->sync_role_capabilities( $role, $clean_slugs, $clean_cap_map );

		$permissions          = get_option( WP_USERRIGHTS_OPTION, array() );
		$permissions[ $role ] = array(
			'menu_slugs'         => $clean_slugs,
			'allowed_categories' => $clean_cats,
			'allowed_page_slugs' => $clean_pages,
			'managed_caps'       => $managed_caps,
		);

		update_option( WP_USERRIGHTS_OPTION, $permissions );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'wp-userrights',
					'role'  => $role,
					'saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Synchronisiert die WordPress-Capabilities einer Rolle mit den erlaubten Menüpunkten.
	 *
	 * - Fügt benötigte Capabilities hinzu (nur solche, die durch dieses Plugin vergeben werden)
	 * - Entfernt zuvor von diesem Plugin vergebene Capabilities, die nicht mehr benötigt werden
	 * - Capabilities die die Rolle bereits vor der Plugin-Verwaltung hatte, werden nie entfernt
	 *
	 * @param string $role_key   Rollen-Schlüssel
	 * @param array  $new_slugs  Aktuell erlaubte Menü-Slugs
	 * @param array  $cap_map    Zuordnung Slug → benötigte Capability (aus Formular)
	 * @return array             Liste der jetzt durch dieses Plugin verwalteten Capabilities
	 */
	private function sync_role_capabilities( $role_key, array $new_slugs, array $cap_map ) {
		$role_obj = get_role( $role_key );
		if ( ! $role_obj ) {
			return array();
		}

		$permissions  = get_option( WP_USERRIGHTS_OPTION, array() );
		$managed_caps = isset( $permissions[ $role_key ]['managed_caps'] )
			? (array) $permissions[ $role_key ]['managed_caps']
			: array();

		// Capabilities berechnen die die neuen Slugs benötigen
		$needed_caps = array();
		foreach ( $new_slugs as $slug ) {
			if ( ! empty( $cap_map[ $slug ] ) ) {
				$needed_caps[] = $cap_map[ $slug ];
			}
		}
		// 'read' ist für jeden Backend-Zugang nötig
		if ( ! empty( $new_slugs ) ) {
			$needed_caps[] = 'read';
		}
		$needed_caps = array_values( array_unique( $needed_caps ) );

		// Neue Capabilities hinzufügen und in managed_caps aufnehmen
		foreach ( $needed_caps as $cap ) {
			$role_obj->add_cap( $cap, true );
			if ( ! in_array( $cap, $managed_caps, true ) ) {
				$managed_caps[] = $cap;
			}
		}

		// Capabilities entfernen die wir früher hinzugefügt haben, aber jetzt nicht mehr brauchen
		$caps_to_remove = array_diff( $managed_caps, $needed_caps );
		foreach ( $caps_to_remove as $cap ) {
			$role_obj->remove_cap( $cap );
		}

		return $needed_caps;
	}
}
