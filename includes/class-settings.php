<?php
/**
 * Admin-Einstellungsseite: Menüpunkte, Rollen und Benutzerzuweisungen konfigurieren.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_UserRights_Settings {

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wp_userrights_save', array( $this, 'save_settings' ) );
		// WP-Benutzerliste: Spalte für zugewiesene Plugin-Rollen
		add_filter( 'manage_users_columns',       array( $this, 'add_users_role_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_users_role_column' ), 10, 3 );
		add_action( 'admin_head-users.php',       array( $this, 'users_page_inline_styles' ) );
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
		// Daten für AJAX-Aufrufe (Benutzerverwaltung)
		wp_localize_script( 'wp-userrights-admin', 'wpurData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpur_toggle_user_role' ),
			'saving'  => __( 'Wird gespeichert …', 'wp-userrights' ),
			'saved'   => __( 'Gespeichert', 'wp-userrights' ),
			'error'   => __( 'Fehler beim Speichern', 'wp-userrights' ),
		) );
	}

	// =========================================================================
	// Hilfsmethoden
	// =========================================================================

	/**
	 * Liest alle aktuell registrierten Menüpunkte aus den globalen Arrays.
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
					$node['children'][] = array(
						'slug'  => $sub_slug,
						'label' => $sub_label ?: $sub_slug,
						'cap'   => isset( $sub[1] ) ? $sub[1] : 'read',
					);
				}
			}

			$tree[] = $node;
		}

		return $tree;
	}

	/** Gibt alle WordPress-Rollen außer administrator zurück. */
	private function get_editable_roles() {
		$all_roles = wp_roles()->roles;
		unset( $all_roles['administrator'] );
		return $all_roles;
	}

	// =========================================================================
	// WP-Benutzerliste: Plugin-Rollen-Spalte
	// =========================================================================

	public function add_users_role_column( $columns ) {
		$columns['wpur_roles'] = '<span class="dashicons dashicons-shield-alt" style="font-size:16px;vertical-align:middle;line-height:1.4;" title="'
			. esc_attr__( 'Plugin-Rollen', 'wp-userrights' ) . '"></span> '
			. esc_html__( 'Plugin-Rollen', 'wp-userrights' );
		return $columns;
	}

	public function render_users_role_column( $output, $column_name, $user_id ) {
		if ( 'wpur_roles' !== $column_name ) {
			return $output;
		}

		$managed_roles = WP_UserRights_Role_Manager::get_managed_roles();
		if ( empty( $managed_roles ) ) {
			return '<span class="wpur-col-dash">—</span>';
		}

		$user       = get_userdata( $user_id );
		$user_roles = (array) $user->roles;
		$all_roles  = wp_roles()->roles;
		$badges     = array();

		foreach ( $managed_roles as $role_slug ) {
			if ( ! in_array( $role_slug, $user_roles, true ) || ! isset( $all_roles[ $role_slug ] ) ) {
				continue;
			}
			$label    = esc_html( translate_user_role( $all_roles[ $role_slug ]['name'] ) );
			$edit_url = esc_url( add_query_arg(
				array( 'page' => 'wp-userrights', 'tab' => 'permissions', 'role' => $role_slug ),
				admin_url( 'admin.php' )
			) );
			$badges[] = '<a href="' . $edit_url . '" class="wpur-col-badge">' . $label . '</a>';
		}

		return ! empty( $badges ) ? implode( ' ', $badges ) : '<span class="wpur-col-dash">—</span>';
	}

	public function users_page_inline_styles() {
		?>
		<style>
		.wpur-col-badge {
			display: inline-block;
			padding: 2px 8px;
			background: #f0f6fc;
			border: 1px solid #c3d9f0;
			border-radius: 3px;
			font-size: 11px;
			color: #135e96;
			text-decoration: none;
			white-space: nowrap;
			margin: 1px 2px 1px 0;
			vertical-align: middle;
		}
		.wpur-col-badge:hover { background: #dbeafe; color: #0c4a8f; }
		.wpur-col-dash { color: #999; }
		</style>
		<?php
	}

	// =========================================================================
	// Haupt-Render: Plugin-Header + Tab-Navigation
	// =========================================================================

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'permissions';
		$tabs        = array(
			'permissions' => array(
				'label' => __( 'Berechtigungen', 'wp-userrights' ),
				'icon'  => 'dashicons-admin-network',
			),
			'rollen'      => array(
				'label' => __( 'Rollen verwalten', 'wp-userrights' ),
				'icon'  => 'dashicons-shield-alt',
			),
			'benutzer'    => array(
				'label' => __( 'Benutzer', 'wp-userrights' ),
				'icon'  => 'dashicons-groups',
			),
		);
		?>
		<div class="wrap wp-userrights-wrap">

			<!-- Plugin-Header -->
			<div class="wp-userrights-plugin-header">
				<span class="dashicons dashicons-shield wp-userrights-logo"></span>
				<div class="wp-userrights-header-text">
					<h1><?php esc_html_e( 'WP User Rights', 'wp-userrights' ); ?></h1>
					<p>
						<?php esc_html_e( 'Backend-Zugriffsrechte pro Benutzerrolle', 'wp-userrights' ); ?>
						&nbsp;·&nbsp;
						<a href="https://loheide.eu" target="_blank" rel="noopener">loheide.eu</a>
					</p>
				</div>
				<?php if ( 'permissions' === $current_tab && isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
				<div class="wp-userrights-save-badge">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Gespeichert', 'wp-userrights' ); ?>
				</div>
				<?php endif; ?>
			</div>

			<!-- Tab-Navigation -->
			<nav class="wp-userrights-tab-nav">
				<?php foreach ( $tabs as $tab_key => $tab ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-userrights', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>"
					class="wpur-tab<?php echo $tab_key === $current_tab ? ' wpur-tab-active' : ''; ?>">
					<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
					<?php echo esc_html( $tab['label'] ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<!-- Tab-Inhalt -->
			<?php
			if ( 'rollen' === $current_tab ) {
				$this->render_roles_tab();
			} elseif ( 'benutzer' === $current_tab ) {
				$this->render_users_tab();
			} else {
				$this->render_permissions_tab();
			}
			?>

		</div>
		<?php
	}

	// =========================================================================
	// Tab 1: Berechtigungen
	// =========================================================================

	private function render_permissions_tab() {
		$roles       = $this->get_editable_roles();
		$permissions = get_option( WP_USERRIGHTS_OPTION, array() );
		$menu_tree   = $this->get_menu_tree();

		$selected_role = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
		if ( empty( $selected_role ) || ! isset( $roles[ $selected_role ] ) ) {
			$selected_role = ! empty( $roles ) ? array_key_first( $roles ) : '';
		}

		$role_perms         = isset( $permissions[ $selected_role ] ) ? $permissions[ $selected_role ] : array();
		$allowed_slugs      = isset( $role_perms['menu_slugs'] ) ? (array) $role_perms['menu_slugs'] : array();
		$allowed_categories = isset( $role_perms['allowed_categories'] ) ? implode( ', ', (array) $role_perms['allowed_categories'] ) : '';
		$allowed_pages      = isset( $role_perms['allowed_page_slugs'] ) ? implode( ', ', (array) $role_perms['allowed_page_slugs'] ) : '';
		$restrict_media     = ! empty( $role_perms['restrict_media'] );

		$builtin_roles = array( 'editor', 'author', 'contributor', 'subscriber' );
		$is_builtin    = in_array( $selected_role, $builtin_roles, true );

		if ( empty( $roles ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Keine bearbeitbaren Rollen gefunden (außer Administrator).', 'wp-userrights' ); ?></p></div>
			<?php return;
		endif;
		?>

		<!-- Rollenauswahl -->
		<div class="wp-userrights-role-selector">
			<span class="role-selector-icon dashicons dashicons-groups"></span>
			<label for="wp-userrights-role-select"><?php esc_html_e( 'Rolle:', 'wp-userrights' ); ?></label>
			<select id="wp-userrights-role-select">
				<?php foreach ( $roles as $role_key => $role_data ) : ?>
				<option value="<?php echo esc_attr( $role_key ); ?>"
					data-url="<?php echo esc_attr( add_query_arg( array( 'page' => 'wp-userrights', 'tab' => 'permissions', 'role' => $role_key ), admin_url( 'admin.php' ) ) ); ?>"
					<?php selected( $selected_role, $role_key ); ?>>
					<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php if ( $is_builtin ) : ?>
			<span class="role-badge role-badge-warning"><span class="dashicons dashicons-warning"></span><?php esc_html_e( 'Standardrolle', 'wp-userrights' ); ?></span>
			<?php else : ?>
			<span class="role-badge role-badge-custom"><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Eigene Rolle', 'wp-userrights' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $is_builtin ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Achtung: WordPress-Standardrolle', 'wp-userrights' ); ?></strong><br>
				<?php printf(
					/* translators: %s: role name */
					esc_html__( 'Die Rolle „%s" ist eine eingebaute WordPress-Rolle mit vordefinierten Berechtigungen. Änderungen hier können das normale WordPress-Verhalten dieser Rolle beeinflussen. Es wird empfohlen, stattdessen eigene benutzerdefinierte Rollen zu verwenden.', 'wp-userrights' ),
					esc_html( translate_user_role( $roles[ $selected_role ]['name'] ) )
				); ?>
			</p>
		</div>
		<?php endif; ?>

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
					<div class="toolbar-left">
						<button type="button" class="button wp-userrights-btn-check" id="wp-userrights-check-all">
							<span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Alle', 'wp-userrights' ); ?>
						</button>
						<button type="button" class="button wp-userrights-btn-uncheck" id="wp-userrights-uncheck-all">
							<span class="dashicons dashicons-dismiss"></span><?php esc_html_e( 'Keine', 'wp-userrights' ); ?>
						</button>
						<span id="wp-userrights-count" class="selection-count">
							<span id="wp-userrights-count-num">0</span> <?php esc_html_e( 'ausgewählt', 'wp-userrights' ); ?>
						</span>
					</div>
					<div class="toolbar-right">
						<div class="wp-userrights-search-box">
							<span class="dashicons dashicons-search"></span>
							<input type="text" id="wp-userrights-search"
								placeholder="<?php esc_attr_e( 'Menüpunkt suchen …', 'wp-userrights' ); ?>"
								autocomplete="off">
						</div>
					</div>
				</div>

				<div id="wp-userrights-cap-preview" class="wp-userrights-cap-preview" style="display:none;">
					<span class="dashicons dashicons-admin-network cap-preview-icon"></span>
					<span class="cap-preview-label"><?php esc_html_e( 'WordPress-Capabilities:', 'wp-userrights' ); ?></span>
					<span id="wp-userrights-cap-list" class="cap-preview-list"></span>
				</div>

				<?php if ( empty( $menu_tree ) ) : ?>
					<p class="description"><?php esc_html_e( 'Keine Menüpunkte gefunden.', 'wp-userrights' ); ?></p>
				<?php else : ?>
				<div class="wp-userrights-menu-tree">
					<?php foreach ( $menu_tree as $top_item ) : ?>
					<div class="menu-item-top">
						<input type="hidden" name="menu_cap_map[<?php echo esc_attr( $top_item['slug'] ); ?>]" value="<?php echo esc_attr( $top_item['cap'] ); ?>">
						<label class="menu-item-label top-level">
							<input type="checkbox" name="menu_slugs[]" value="<?php echo esc_attr( $top_item['slug'] ); ?>"
								class="menu-checkbox top-level-checkbox"
								<?php checked( in_array( $top_item['slug'], $allowed_slugs, true ) ); ?>>
							<span class="dashicons dashicons-menu-alt"></span>
							<?php echo esc_html( $top_item['label'] ); ?>
							<code class="slug-hint"><?php echo esc_html( $top_item['slug'] ); ?></code>
						</label>
						<?php if ( ! empty( $top_item['children'] ) ) : ?>
						<div class="submenu-items">
							<?php foreach ( $top_item['children'] as $child ) : ?>
							<input type="hidden" name="menu_parent_map[<?php echo esc_attr( $child['slug'] ); ?>]" value="<?php echo esc_attr( $top_item['slug'] ); ?>">
							<input type="hidden" name="menu_cap_map[<?php echo esc_attr( $child['slug'] ); ?>]" value="<?php echo esc_attr( $child['cap'] ); ?>">
							<label class="menu-item-label sub-level">
								<input type="checkbox" name="menu_slugs[]" value="<?php echo esc_attr( $child['slug'] ); ?>"
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
				<p class="description"><?php esc_html_e( 'Optional: Schränken Sie ein, welche Beiträge und Seiten diese Rolle sehen darf. Leer = alle sichtbar.', 'wp-userrights' ); ?></p>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wp-userrights-categories"><?php esc_html_e( 'Nur Beiträge in Kategorien', 'wp-userrights' ); ?></label></th>
						<td>
							<input type="text" id="wp-userrights-categories" name="allowed_categories"
								value="<?php echo esc_attr( $allowed_categories ); ?>" class="regular-text" placeholder="team1, aktuell">
							<p class="description"><?php esc_html_e( 'Kategorie-Slugs, kommagetrennt.', 'wp-userrights' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp-userrights-pages"><?php esc_html_e( 'Nur Seiten mit diesen Slugs', 'wp-userrights' ); ?></label></th>
						<td>
							<input type="text" id="wp-userrights-pages" name="allowed_page_slugs"
								value="<?php echo esc_attr( $allowed_pages ); ?>" class="regular-text" placeholder="team1, team2">
							<p class="description"><?php esc_html_e( 'Seiten-Slugs, kommagetrennt.', 'wp-userrights' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mediathek einschränken', 'wp-userrights' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="restrict_media" value="1" <?php checked( $restrict_media ); ?>>
								<?php esc_html_e( 'Nur eigene hochgeladene Medien anzeigen', 'wp-userrights' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Die Rolle sieht in der Mediathek und im Medien-Modal nur Dateien, die sie selbst hochgeladen hat.', 'wp-userrights' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Einstellungen speichern', 'wp-userrights' ); ?></button>
			</p>
		</form>
		<?php
	}

	// =========================================================================
	// Tab 2: Rollen verwalten
	// =========================================================================

	private function render_roles_tab() {
		$managed_roles = WP_UserRights_Role_Manager::get_managed_roles();
		$all_roles     = wp_roles()->roles;
		?>

		<!-- Notices -->
		<?php if ( isset( $_GET['role_created'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rolle erfolgreich erstellt.', 'wp-userrights' ); ?></p></div>
		<?php elseif ( isset( $_GET['role_deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rolle wurde gelöscht.', 'wp-userrights' ); ?></p></div>
		<?php elseif ( isset( $_GET['role_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
			<?php
			$errors = array(
				'empty'  => __( 'Bitte Name und Slug angeben.', 'wp-userrights' ),
				'exists' => __( 'Eine Rolle mit diesem Slug existiert bereits.', 'wp-userrights' ),
				'failed' => __( 'Die Rolle konnte nicht erstellt werden.', 'wp-userrights' ),
			);
			$code = sanitize_key( $_GET['role_error'] );
			echo esc_html( isset( $errors[ $code ] ) ? $errors[ $code ] : __( 'Unbekannter Fehler.', 'wp-userrights' ) );
			?>
			</p>
		</div>
		<?php endif; ?>

		<!-- Neue Rolle erstellen -->
		<div class="wp-userrights-section">
			<h2><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Neue Rolle erstellen', 'wp-userrights' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpur_create_role', 'wpur_nonce' ); ?>
				<input type="hidden" name="action" value="wpur_create_role">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wpur-role-name"><?php esc_html_e( 'Rollenname', 'wp-userrights' ); ?></label></th>
						<td>
							<input type="text" id="wpur-role-name" name="role_name" class="regular-text"
								placeholder="<?php esc_attr_e( 'z. B. Team 1', 'wp-userrights' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpur-role-slug"><?php esc_html_e( 'Rollen-ID (Slug)', 'wp-userrights' ); ?></label></th>
						<td>
							<input type="text" id="wpur-role-slug" name="role_slug" class="regular-text"
								placeholder="<?php esc_attr_e( 'team1', 'wp-userrights' ); ?>" required
								pattern="[a-z0-9_\-]+" title="<?php esc_attr_e( 'Nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche.', 'wp-userrights' ); ?>">
							<p class="description"><?php esc_html_e( 'Kleinbuchstaben, keine Sonderzeichen. Kann nach dem Erstellen nicht geändert werden.', 'wp-userrights' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Rolle erstellen', 'wp-userrights' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Verwaltete Rollen -->
		<div class="wp-userrights-section">
			<h2><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Von diesem Plugin verwaltete Rollen', 'wp-userrights' ); ?></h2>

			<?php if ( empty( $managed_roles ) ) : ?>
			<p class="wpur-empty-state">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Noch keine eigenen Rollen erstellt. Nutzen Sie das Formular oben um Ihre erste Rolle anzulegen.', 'wp-userrights' ); ?>
			</p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped wpur-roles-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Rollenname', 'wp-userrights' ); ?></th>
						<th><?php esc_html_e( 'Rollen-ID', 'wp-userrights' ); ?></th>
						<th><?php esc_html_e( 'Zugewiesene Benutzer', 'wp-userrights' ); ?></th>
						<th><?php esc_html_e( 'Berechtigungen', 'wp-userrights' ); ?></th>
						<th><?php esc_html_e( 'Aktionen', 'wp-userrights' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $managed_roles as $role_slug ) :
						if ( ! isset( $all_roles[ $role_slug ] ) ) continue;
						$role_name   = translate_user_role( $all_roles[ $role_slug ]['name'] );
						$user_count  = count( get_users( array( 'role' => $role_slug, 'fields' => 'ID' ) ) );
						$permissions = get_option( WP_USERRIGHTS_OPTION, array() );
						$slug_count  = isset( $permissions[ $role_slug ]['menu_slugs'] ) ? count( $permissions[ $role_slug ]['menu_slugs'] ) : 0;
					?>
					<tr>
						<td><strong><?php echo esc_html( $role_name ); ?></strong></td>
						<td><code><?php echo esc_html( $role_slug ); ?></code></td>
						<td>
							<span class="wpur-count-badge"><?php echo esc_html( $user_count ); ?></span>
						</td>
						<td>
							<?php if ( $slug_count > 0 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-userrights', 'tab' => 'permissions', 'role' => $role_slug ), admin_url( 'admin.php' ) ) ); ?>">
								<?php printf( esc_html( _n( '%d Menüpunkt', '%d Menüpunkte', $slug_count, 'wp-userrights' ) ), $slug_count ); ?>
							</a>
							<?php else : ?>
							<span class="wpur-no-perms"><?php esc_html_e( 'Keine konfiguriert', 'wp-userrights' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-userrights', 'tab' => 'permissions', 'role' => $role_slug ), admin_url( 'admin.php' ) ) ); ?>"
								class="button button-small">
								<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Bearbeiten', 'wp-userrights' ); ?>
							</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpur-delete-form">
								<?php wp_nonce_field( 'wpur_delete_role', 'wpur_nonce' ); ?>
								<input type="hidden" name="action" value="wpur_delete_role">
								<input type="hidden" name="role_slug" value="<?php echo esc_attr( $role_slug ); ?>">
								<button type="submit" class="button button-small wpur-btn-delete"
									onclick="return confirm('<?php printf( esc_attr__( 'Rolle „%s" wirklich löschen? Alle Benutzerzuweisungen werden entfernt.', 'wp-userrights' ), esc_attr( $role_name ) ); ?>')">
									<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Löschen', 'wp-userrights' ); ?>
								</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Tab 3: Benutzer
	// =========================================================================

	private function render_users_tab() {
		$managed_roles = WP_UserRights_Role_Manager::get_managed_roles();
		$all_roles     = wp_roles()->roles;

		// Bulk-Aktion: Ergebnis-Hinweis
		if ( isset( $_GET['bulk_done'] ) ) {
			$bulk_count  = absint( $_GET['bulk_done'] );
			$bulk_action = isset( $_GET['bulk_action'] ) ? sanitize_key( $_GET['bulk_action'] ) : 'assign';
			$bulk_msg    = ( 'assign' === $bulk_action )
				? sprintf( _n( '%d Benutzer wurde die Rolle zugewiesen.', '%d Benutzern wurde die Rolle zugewiesen.', $bulk_count, 'wp-userrights' ), $bulk_count )
				: sprintf( _n( 'Rolle wurde %d Benutzer entzogen.', 'Rolle wurde %d Benutzern entzogen.', $bulk_count, 'wp-userrights' ), $bulk_count );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $bulk_msg ) . '</p></div>';
		} elseif ( isset( $_GET['bulk_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Keine Benutzer oder Rolle ausgewählt.', 'wp-userrights' ) . '</p></div>';
		}
		?>

		<?php if ( empty( $managed_roles ) ) : ?>
		<div class="wp-userrights-section">
			<p class="wpur-empty-state">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Zuerst eigene Rollen unter „Rollen verwalten" erstellen, bevor Benutzern Rollen zugewiesen werden können.', 'wp-userrights' ); ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-userrights', 'tab' => 'rollen' ), admin_url( 'admin.php' ) ) ); ?>"
					class="button button-small" style="margin-left:10px;">
					<?php esc_html_e( 'Zur Rollenverwaltung', 'wp-userrights' ); ?>
				</a>
			</p>
		</div>
		<?php return; endif;

		// Suche und Pagination
		$per_page   = 20;
		$paged      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$search     = isset( $_GET['user_search'] ) ? sanitize_text_field( wp_unslash( $_GET['user_search'] ) ) : '';

		$query_args = array(
			'role__not_in' => array( 'administrator' ),
			'orderby'      => 'display_name',
			'order'        => 'ASC',
			'number'       => $per_page,
			'offset'       => ( $paged - 1 ) * $per_page,
			'count_total'  => true,
		);

		if ( ! empty( $search ) ) {
			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = array( 'display_name', 'user_email', 'user_login' );
		}

		$user_query  = new WP_User_Query( $query_args );
		$users       = $user_query->get_results();
		$total_users = (int) $user_query->get_total();
		$total_pages = (int) ceil( $total_users / $per_page );
		?>

		<div class="wp-userrights-section">
			<h2>
				<span class="dashicons dashicons-groups"></span>
				<?php esc_html_e( 'Rollen zuweisen', 'wp-userrights' ); ?>
				<span class="wpur-count-badge" style="margin-left:8px;"><?php echo esc_html( $total_users ); ?></span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Weisen Sie Benutzern zusätzlich zu ihrer Basis-Rolle eine eigene Rolle zu. Die Zuweisung erfolgt sofort — kein Speichern nötig.', 'wp-userrights' ); ?>
			</p>

			<!-- Suche -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="wpur-user-search-form">
				<input type="hidden" name="page" value="wp-userrights">
				<input type="hidden" name="tab" value="benutzer">
				<div class="wpur-user-search-wrap">
					<span class="dashicons dashicons-search"></span>
					<input type="text" id="wpur-user-search" name="user_search"
						value="<?php echo esc_attr( $search ); ?>"
						placeholder="<?php esc_attr_e( 'Name oder E-Mail suchen …', 'wp-userrights' ); ?>"
						autocomplete="off">
					<?php if ( ! empty( $search ) ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-userrights', 'tab' => 'benutzer' ), admin_url( 'admin.php' ) ) ); ?>"
						class="wpur-search-clear" title="<?php esc_attr_e( 'Suche zurücksetzen', 'wp-userrights' ); ?>">
						<span class="dashicons dashicons-dismiss"></span>
					</a>
					<?php endif; ?>
				</div>
			</form>

			<?php if ( empty( $users ) ) : ?>
			<p class="wpur-empty-state">
				<span class="dashicons dashicons-info"></span>
				<?php
				if ( ! empty( $search ) ) {
					printf(
						/* translators: %s: search term */
						esc_html__( 'Keine Benutzer gefunden für „%s".', 'wp-userrights' ),
						esc_html( $search )
					);
				} else {
					esc_html_e( 'Keine Benutzer gefunden.', 'wp-userrights' );
				}
				?>
			</p>
			<?php else :
				$col_count = 3 + count( array_filter( $managed_roles, function( $s ) use ( $all_roles ) { return isset( $all_roles[ $s ] ); } ) );
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpur-bulk-form">
				<?php wp_nonce_field( 'wpur_bulk_assign', 'wpur_bulk_nonce' ); ?>
				<input type="hidden" name="action" value="wpur_bulk_assign">

				<!-- Bulk-Aktionsleiste -->
				<div class="wpur-bulk-bar" id="wpur-bulk-bar">
					<span class="wpur-bulk-selected-count">
						<span id="wpur-bulk-count">0</span> <?php esc_html_e( 'ausgewählt', 'wp-userrights' ); ?>
					</span>
					<select name="bulk_role" class="wpur-bulk-role-select">
						<option value=""><?php esc_html_e( '— Rolle wählen —', 'wp-userrights' ); ?></option>
						<?php foreach ( $managed_roles as $rs ) :
							if ( ! isset( $all_roles[ $rs ] ) ) continue; ?>
						<option value="<?php echo esc_attr( $rs ); ?>"><?php echo esc_html( translate_user_role( $all_roles[ $rs ]['name'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" name="bulk_action" value="assign" class="button button-primary">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Zuweisen', 'wp-userrights' ); ?>
					</button>
					<button type="submit" name="bulk_action" value="remove" class="button wpur-btn-delete">
						<span class="dashicons dashicons-minus"></span> <?php esc_html_e( 'Entziehen', 'wp-userrights' ); ?>
					</button>
				</div>

				<div class="wpur-user-table-wrap">
					<table class="wp-list-table widefat striped wpur-user-table">
						<thead>
							<tr>
								<th class="col-check">
									<input type="checkbox" id="wpur-select-all" title="<?php esc_attr_e( 'Alle auswählen', 'wp-userrights' ); ?>">
								</th>
								<th class="col-user"><?php esc_html_e( 'Benutzer', 'wp-userrights' ); ?></th>
								<th class="col-base-role"><?php esc_html_e( 'Basis-Rolle', 'wp-userrights' ); ?></th>
								<th class="col-roles"><?php esc_html_e( 'Plugin-Rollen', 'wp-userrights' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $users as $user ) :
								$user_roles  = (array) $user->roles;
								$base_roles  = array_diff( $user_roles, $managed_roles );
								$base_labels = array();
								foreach ( $base_roles as $br ) {
									$base_labels[] = isset( $all_roles[ $br ] ) ? translate_user_role( $all_roles[ $br ]['name'] ) : $br;
								}
								$is_subscriber_only = ( $user_roles === array( 'subscriber' ) );
								// Unzugewiesene verwaltete Rollen für das Dropdown
								$unassigned_roles = array_filter( $managed_roles, function( $rs ) use ( $user_roles, $all_roles ) {
									return ! in_array( $rs, $user_roles, true ) && isset( $all_roles[ $rs ] );
								} );
							?>
							<tr data-user-id="<?php echo esc_attr( $user->ID ); ?>">
								<td class="col-check">
									<input type="checkbox" name="bulk_users[]"
										value="<?php echo esc_attr( $user->ID ); ?>"
										class="wpur-bulk-checkbox">
								</td>
								<td class="col-user">
									<div class="wpur-user-info">
										<?php echo get_avatar( $user->ID, 28, '', '', array( 'class' => 'wpur-avatar' ) ); ?>
										<div>
											<strong><?php echo esc_html( $user->display_name ); ?></strong>
											<br><small class="wpur-email"><?php echo esc_html( $user->user_email ); ?></small>
										</div>
									</div>
								</td>
								<td class="col-base-role">
									<?php if ( ! empty( $base_labels ) ) : ?>
									<span class="wpur-base-role-badge"><?php echo esc_html( implode( ', ', $base_labels ) ); ?></span>
									<?php if ( $is_subscriber_only ) : ?>
									<br><small class="wpur-subscriber-hint">
										<span class="dashicons dashicons-lock"></span>
										<?php esc_html_e( 'Kein Backend-Zugriff', 'wp-userrights' ); ?>
									</small>
									<?php endif; ?>
									<?php else : ?>
									<span class="wpur-text-muted"><?php esc_html_e( '(keine)', 'wp-userrights' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="col-roles">
									<div class="wpur-role-chips" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
										<?php foreach ( $managed_roles as $role_slug ) :
											if ( ! isset( $all_roles[ $role_slug ] ) ) continue;
											if ( ! in_array( $role_slug, $user_roles, true ) ) continue;
											$chip_label = esc_html( translate_user_role( $all_roles[ $role_slug ]['name'] ) );
										?>
										<span class="wpur-chip" data-role="<?php echo esc_attr( $role_slug ); ?>">
											<?php echo $chip_label; ?>
											<button type="button" class="wpur-chip-remove"
												data-user-id="<?php echo esc_attr( $user->ID ); ?>"
												data-role="<?php echo esc_attr( $role_slug ); ?>"
												title="<?php esc_attr_e( 'Entfernen', 'wp-userrights' ); ?>">×</button>
										</span>
										<?php endforeach; ?>

										<?php if ( ! empty( $unassigned_roles ) ) : ?>
										<div class="wpur-add-role-wrap">
											<button type="button" class="wpur-add-role-btn"
												title="<?php esc_attr_e( 'Rolle hinzufügen', 'wp-userrights' ); ?>">
												<span class="dashicons dashicons-plus-alt2"></span>
											</button>
											<div class="wpur-role-dropdown">
												<?php foreach ( $unassigned_roles as $role_slug ) : ?>
												<button type="button" class="wpur-role-option"
													data-user-id="<?php echo esc_attr( $user->ID ); ?>"
													data-role="<?php echo esc_attr( $role_slug ); ?>">
													<?php echo esc_html( translate_user_role( $all_roles[ $role_slug ]['name'] ) ); ?>
												</button>
												<?php endforeach; ?>
											</div>
										</div>
										<?php endif; ?>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</form>

			<?php if ( $total_pages > 1 ) :
				$base_url = add_query_arg( array( 'page' => 'wp-userrights', 'tab' => 'benutzer' ), admin_url( 'admin.php' ) );
				if ( ! empty( $search ) ) {
					$base_url = add_query_arg( 'user_search', urlencode( $search ), $base_url );
				}
				$pagination = paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'type'      => 'plain',
				) );
			?>
			<div class="wpur-pagination">
				<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="wpur-pagination-info">
					<?php printf(
						/* translators: 1: first item, 2: last item, 3: total */
						esc_html__( '%1$d–%2$d von %3$d Benutzern', 'wp-userrights' ),
						( ( $paged - 1 ) * $per_page ) + 1,
						min( $paged * $per_page, $total_users ),
						$total_users
					); ?>
				</span>
			</div>
			<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Berechtigungen speichern (Permissions Tab)
	// =========================================================================

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

		// Eltern-Slug automatisch miteinschließen
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

		// Mediathek einschränken
		$restrict_media = ! empty( $_POST['restrict_media'] );

		// Capability-Synchronisierung
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
			'restrict_media'     => $restrict_media,
			'managed_caps'       => $managed_caps,
		);

		update_option( WP_USERRIGHTS_OPTION, $permissions );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'wp-userrights', 'tab' => 'permissions', 'role' => $role, 'saved' => '1' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Synchronisiert WordPress-Capabilities einer Rolle mit den erlaubten Menüpunkten.
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

		$needed_caps = array();
		foreach ( $new_slugs as $slug ) {
			if ( ! empty( $cap_map[ $slug ] ) ) {
				$needed_caps[] = $cap_map[ $slug ];
			}
		}
		if ( ! empty( $new_slugs ) ) {
			$needed_caps[] = 'read';
		}
		$needed_caps = array_values( array_unique( $needed_caps ) );

		// Erweiterte Capabilities: edit_pages allein reicht nicht um fremde/veröffentlichte
		// Einträge zu bearbeiten — zugehörige Caps automatisch mitgeben.
		$cap_expansions = array(
			'edit_posts'   => array( 'edit_others_posts', 'edit_published_posts', 'publish_posts' ),
			'edit_pages'   => array( 'edit_others_pages', 'edit_published_pages', 'publish_pages' ),
			'delete_posts' => array( 'delete_others_posts', 'delete_published_posts' ),
			'delete_pages' => array( 'delete_others_pages', 'delete_published_pages' ),
		);
		$extra = array();
		foreach ( $needed_caps as $cap ) {
			if ( isset( $cap_expansions[ $cap ] ) ) {
				$extra = array_merge( $extra, $cap_expansions[ $cap ] );
			}
		}
		$needed_caps = array_values( array_unique( array_merge( $needed_caps, $extra ) ) );

		foreach ( $needed_caps as $cap ) {
			$role_obj->add_cap( $cap, true );
			if ( ! in_array( $cap, $managed_caps, true ) ) {
				$managed_caps[] = $cap;
			}
		}

		foreach ( array_diff( $managed_caps, $needed_caps ) as $cap ) {
			$role_obj->remove_cap( $cap );
		}

		return $needed_caps;
	}
}
