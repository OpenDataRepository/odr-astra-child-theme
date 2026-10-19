<?php

/**
 * Admin side of the shortcut-search gate: a settings page under Settings -> ODR Search Gate, a
 * button to rebuild the allowlist index, and a daily cron job that rebuilds it on its own.
 *
 * See odr_search_gate.php for what the settings actually do.
 */


/**
 * Registers the settings page.
 */
add_action( 'admin_menu', 'odr_sg_admin_menu' );

function odr_sg_admin_menu() {
    add_options_page(
        'ODR Search Gate',
        'ODR Search Gate',
        'manage_options',
        'odr-search-gate',
        'odr_sg_settings_page'
    );
}


/**
 * Registers the option and its fields.
 */
add_action( 'admin_init', 'odr_sg_register_settings' );

function odr_sg_register_settings() {
    register_setting( 'odr_sg_settings_group', ODR_SG_OPTION, array(
        'sanitize_callback' => 'odr_sg_sanitize_settings',
        'default' => odr_sg_defaults(),
    ) );
}

/**
 * Cleans up submitted settings.
 *
 * Merges over whatever is already stored rather than replacing it, so a field that isn't on the
 * form (or a checkbox the browser didn't send) can't silently wipe a stored value.
 *
 * @param array $input
 *
 * @return array
 */
function odr_sg_sanitize_settings( $input ) {
    $current = odr_sg_settings();
    if ( ! is_array( $input ) )
        return $current;

    $clean = $current;

    foreach ( array( 'recaptcha_site_key', 'recaptcha_secret' ) as $key ) {
        if ( isset( $input[ $key ] ) )
            $clean[ $key ] = trim( sanitize_text_field( $input[ $key ] ) );
    }

    // a checkbox is simply absent when unticked, and this form always posts every checkbox's
    // hidden companion, so an absent key here means "off"
    $clean['use_name_lists'] = empty( $input['use_name_lists'] ) ? 0 : 1;
    $clean['allow_verified_crawlers'] = empty( $input['allow_verified_crawlers'] ) ? 0 : 1;

    if ( isset( $input['block_seconds'] ) ) {
        $seconds = (int)$input['block_seconds'];
        $clean['block_seconds'] = ( $seconds < 60 ) ? 60 : $seconds;
    }

    if ( isset( $input['odr_web_dir'] ) ) {
        $directory = rtrim( trim( sanitize_text_field( $input['odr_web_dir'] ) ), '/' );
        $clean['odr_web_dir'] = $directory;
    }

    if ( isset( $input['index_path'] ) ) {
        $clean['index_path'] = rtrim( trim( sanitize_text_field( $input['index_path'] ) ), '/' );
    }

    if ( isset( $input['odr_base_path'] ) ) {
        $path = trim( sanitize_text_field( $input['odr_base_path'] ) );
        $clean['odr_base_path'] = ( $path === '' ) ? '' : '/' . trim( $path, '/' );
    }

    return $clean;
}


/**
 * Renders the settings page.
 */
function odr_sg_settings_page() {
    if ( ! current_user_can( 'manage_options' ) )
        return;

    $settings = odr_sg_settings();
    $index = odr_sg_index();
    $index_path = odr_sg_index_path();

    ?>
    <div class="wrap">
        <h1>ODR Search Gate</h1>

        <?php $uses_lists = ! empty( $settings['use_name_lists'] ); ?>

        <p>
            Controls the shortcut search routes &mdash; <code>/&lt;mineral&gt;</code>,
            <code>/ima/&lt;mineral&gt;</code> and <code>/amcsd/&lt;mineral&gt;</code>.
            <?php if ( $uses_lists ) : ?>
                Terms on the allowed lists search immediately; anything else asks an anonymous
                visitor to pass a reCAPTCHA first, and a visitor who fails is refused for a while.
            <?php else : ?>
                An anonymous visitor is asked to pass a reCAPTCHA, and one who fails is refused for
                a while. This site has no mineral name lists, so nothing is allowed through
                un-challenged except logged-in users and &mdash; if enabled below &mdash; verified
                search crawlers.
            <?php endif; ?>
        </p>

        <?php if ( $uses_lists ) : ?>
        <h2>Allowlist index</h2>
        <table class="widefat striped" style="max-width:52rem">
            <tbody>
            <tr>
                <td style="width:14rem"><strong>Mineral names</strong></td>
                <td><?php echo number_format( count( $index['minerals'] ) ); ?></td>
            </tr>
            <tr>
                <td><strong>AMCSD names</strong></td>
                <td><?php echo number_format( count( $index['amcsd'] ) ); ?></td>
            </tr>
            <tr>
                <td><strong>Built</strong></td>
                <td>
                    <?php
                    if ( empty( $index['built'] ) )
                        echo '<span style="color:#b32d2e">never &mdash; every anonymous search is being challenged</span>';
                    else
                        echo esc_html( gmdate( 'Y-m-d H:i', (int)$index['built'] ) . ' UTC' );
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>File</strong></td>
                <td><code><?php echo esc_html( $index_path ); ?></code></td>
            </tr>
            <?php if ( ! empty( $index['sources'] ) ) : ?>
                <tr>
                    <td><strong>Sources</strong></td>
                    <td>
                        <?php foreach ( $index['sources'] as $basename => $meta ) : ?>
                            <code><?php echo esc_html( $basename ); ?></code>
                            &mdash; <?php echo number_format( (int)$meta['matched'] ); ?> entries,
                            updated <?php echo esc_html( gmdate( 'Y-m-d', (int)$meta['mtime'] ) ); ?><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="odr_sg_rebuild_index">
            <?php wp_nonce_field( 'odr_sg_rebuild_index' ); ?>
            <p>
                <button type="submit" class="button">Rebuild index now</button>
                <span class="description">
                    <?php
                    $next = wp_next_scheduled( 'odr_sg_hourly_rebuild' );
                    if ( $next )
                        printf(
                            'Rebuilds hourly when a source file changes; next check %s.',
                            esc_html( human_time_diff( time(), $next ) . ' from now' )
                        );
                    else
                        echo 'Hourly rebuild is <strong>not scheduled</strong>.';
                    ?>
                    Needs the ODR web directory below to be set.
                    <?php if ( odr_sg_index_is_stale() ) : ?>
                        <br><strong>A source file has changed since the index was built.</strong>
                    <?php endif; ?>
                </span>
            </p>
        </form>

        <?php endif; /* $uses_lists */ ?>

        <h2>Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'odr_sg_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Mineral name lists</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo ODR_SG_OPTION; ?>[use_name_lists]"
                                   value="1" <?php checked( $uses_lists ); ?>>
                            This site has lists of search terms that skip the challenge
                        </label>
                        <p class="description">
                            Only the mineral sites have these. With this off, the gate is just
                            logged-in users, verified crawlers and reCAPTCHA &mdash; no index is
                            built, no files are read, and the settings for them are hidden.
                            Save the page after changing this to see the rest.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odr_sg_site_key">reCAPTCHA site key</label></th>
                    <td>
                        <input type="text" class="regular-text" id="odr_sg_site_key"
                               name="<?php echo ODR_SG_OPTION; ?>[recaptcha_site_key]"
                               value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>">
                        <p class="description">
                            A reCAPTCHA <strong>v2 &ldquo;I&rsquo;m not a robot&rdquo;</strong> key pair, from
                            <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">google.com/recaptcha/admin</a>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odr_sg_secret">reCAPTCHA secret key</label></th>
                    <td>
                        <input type="password" class="regular-text" id="odr_sg_secret"
                               name="<?php echo ODR_SG_OPTION; ?>[recaptcha_secret]"
                               value="<?php echo esc_attr( $settings['recaptcha_secret'] ); ?>"
                               autocomplete="new-password">
                        <p class="description">
                            Until both keys are set, an unlisted anonymous search gets the minimal 404
                            instead of a challenge.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Verified crawlers</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo ODR_SG_OPTION; ?>[allow_verified_crawlers]"
                                   value="1" <?php checked( ! empty( $settings['allow_verified_crawlers'] ) ); ?>>
                            Let verified Google, Bing and DuckDuckGo crawlers run unlisted searches
                        </label>
                        <p class="description">
                            Checked by address against the published crawler ranges in
                            <code>crawler_ips.php</code>, never by user agent. Off by default: a crawler
                            guessing urls is still a bot spending a search.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odr_sg_block_seconds">Ban length</label></th>
                    <td>
                        <input type="number" min="60" step="60" id="odr_sg_block_seconds"
                               name="<?php echo ODR_SG_OPTION; ?>[block_seconds]"
                               value="<?php echo esc_attr( (int)$settings['block_seconds'] ); ?>">
                        seconds
                        <p class="description">
                            How long a client that fails is refused &mdash; and how long one that passes is
                            trusted, so a visitor isn&rsquo;t re-challenged on every search. 7200 is two hours.
                        </p>
                    </td>
                </tr>
                <?php if ( $uses_lists ) : ?>
                <tr>
                    <th scope="row"><label for="odr_sg_web_dir">ODR web directory</label></th>
                    <td>
                        <input type="text" class="large-text code" id="odr_sg_web_dir"
                               name="<?php echo ODR_SG_OPTION; ?>[odr_web_dir]"
                               value="<?php echo esc_attr( $settings['odr_web_dir'] ); ?>"
                               placeholder="/home/rruff/data-publisher/web">
                        <p class="description">
                            <?php
                            $web_dir = $settings['odr_web_dir'];
                            if ( $web_dir === '' ) {
                                echo 'Not set, so the index can&rsquo;t be rebuilt and no visitor is sent to a cached page.';
                            }
                            else {
                                $names = $web_dir . '/uploads/IMA';
                                $static = $web_dir . '/uploads/static';
                                printf(
                                    '<code>%s</code> %s &middot; <code>%s</code> %s',
                                    esc_html( $names ),
                                    is_dir( $names ) ? 'found' : '<span style="color:#b32d2e">missing</span>',
                                    esc_html( $static ),
                                    is_dir( $static ) ? 'found' : '<span style="color:#b32d2e">missing</span>'
                                );
                            }
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odr_sg_index_path">Index file</label></th>
                    <td>
                        <input type="text" class="large-text code" id="odr_sg_index_path"
                               name="<?php echo ODR_SG_OPTION; ?>[index_path]"
                               value="<?php echo esc_attr( $settings['index_path'] ); ?>"
                               placeholder="<?php echo esc_attr( odr_sg_index_path() ); ?>">
                        <p class="description">
                            Leave blank to keep the generated index in <code>wp-content/uploads</code>.
                            Set it when that directory is read-only, or when cron writes the index as
                            another user.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="odr_sg_base_path">ODR url path</label></th>
                    <td>
                        <input type="text" class="regular-text code" id="odr_sg_base_path"
                               name="<?php echo ODR_SG_OPTION; ?>[odr_base_path]"
                               value="<?php echo esc_attr( $settings['odr_base_path'] ); ?>"
                               placeholder="/odr_rruff">
                        <p class="description">
                            Where ODR is served from on this site. Used to build cached page links.
                        </p>
                    </td>
                </tr>
                <?php endif; /* $uses_lists */ ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


/**
 * Handles the "Rebuild index now" button.
 */
add_action( 'admin_post_odr_sg_rebuild_index', 'odr_sg_handle_rebuild' );

function odr_sg_handle_rebuild() {
    if ( ! current_user_can( 'manage_options' ) )
        wp_die( 'Not allowed.' );

    check_admin_referer( 'odr_sg_rebuild_index' );

    $result = odr_sg_build_index();

    if ( is_string( $result ) )
        set_transient( 'odr_sg_rebuild_notice', array( 'error', $result ), 60 );
    else
        set_transient( 'odr_sg_rebuild_notice', array(
            'success',
            sprintf( '%s mineral names and %s AMCSD names indexed.',
                number_format( $result['minerals'] ), number_format( $result['amcsd'] ) ),
        ), 60 );

    wp_safe_redirect( admin_url( 'options-general.php?page=odr-search-gate' ) );
    exit;
}


/**
 * Shows the result of a rebuild, and warns when the gate can't do its job.
 */
add_action( 'admin_notices', 'odr_sg_admin_notices' );

function odr_sg_admin_notices() {
    if ( ! current_user_can( 'manage_options' ) )
        return;

    $notice = get_transient( 'odr_sg_rebuild_notice' );
    if ( is_array( $notice ) ) {
        delete_transient( 'odr_sg_rebuild_notice' );
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            $notice[0] === 'error' ? 'error' : 'success',
            esc_html( $notice[1] )
        );
    }

    // only nag on this theme's own settings screen
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'settings_page_odr-search-gate' )
        return;

    if ( ! odr_sg_recaptcha_ready() ) {
        echo '<div class="notice notice-warning"><p>'
           . 'reCAPTCHA keys are not set, so any anonymous search that isn\'t on an allowed list '
           . 'gets a 404 rather than a chance to prove itself.'
           . '</p></div>';
    }

    if ( ! odr_sg_setting( 'use_name_lists' ) )
        return;

    $index = odr_sg_index();
    if ( empty( $index['built'] ) ) {
        echo '<div class="notice notice-warning"><p>'
           . 'The allowlist index has never been built, so <em>every</em> anonymous search is being '
           . 'challenged. Set the ODR web directory below and use &ldquo;Rebuild index now&rdquo;.'
           . '</p></div>';
    }
}
