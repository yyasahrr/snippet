<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NGT_HDR_Renderer {
	private $assets_enqueued = false;

	public function render( $raw_settings = array() ) {
		$settings = $this->sanitize_settings( $raw_settings );
		$this->enqueue_assets();

		$instance_id   = wp_unique_id( 'ngt-hdr-' );
		$main_nav      = in_array( $settings['style'], array( '1', '4', '6', '7', '9' ), true );
		$secondary_nav = in_array( $settings['style'], array( '2', '3', '8', '10' ), true );
		$menu_html     = $main_nav ? $this->get_menu_html( $settings['menu'], $instance_id . '-desktop-menu', 'ngt-hdr-menu' ) : '';
		$secondary_html = $secondary_nav ? $this->get_menu_html( $settings['menu'], $instance_id . '-secondary-menu', 'ngt-hdr-menu' ) : '';
		$mobile_menu   = $this->get_menu_html( $settings['menu'], $instance_id . '-mobile-menu', 'ngt-hdr-mobile-menu' );
		$logo_html     = $this->get_logo_html( $settings );
		$classes       = $this->get_wrapper_classes( $settings );
		$style_attr    = $this->get_css_variables( $settings );
		$responsive_css = $this->get_responsive_css( $settings, $instance_id );

		ob_start();
		?>
		<style id="<?php echo esc_attr( $instance_id . '-responsive' ); ?>"><?php echo $responsive_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
		<header
			id="<?php echo esc_attr( $instance_id ); ?>"
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			style="<?php echo esc_attr( $style_attr ); ?>"
			data-ngt-header
			data-layout="<?php echo esc_attr( $settings['style'] ); ?>"
			data-collapse-at="<?php echo esc_attr( $settings['mobile_breakpoint'] ); ?>"
		>
			<div class="ngt-hdr-shell">
				<div class="ngt-hdr-main">
					<div class="ngt-hdr-brand">
						<?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<nav class="ngt-hdr-navigation" aria-label="<?php esc_attr_e( 'منوی اصلی', 'noghte-smart-headers' ); ?>">
						<?php echo $menu_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</nav>

					<div class="ngt-hdr-actions">
						<?php echo $this->get_cta_html( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->get_search_button_html( $settings, $instance_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->get_account_html( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->get_cart_html( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button
							id="<?php echo esc_attr( $instance_id . '-menu-trigger' ); ?>"
							type="button"
							class="ngt-hdr-icon-button ngt-hdr-menu-toggle ngt-hdr-menu-trigger"
							aria-label="<?php esc_attr_e( 'باز کردن منو', 'noghte-smart-headers' ); ?>"
							aria-controls="<?php echo esc_attr( $instance_id . '-drawer' ); ?>"
							aria-expanded="false"
							data-ngt-menu-open
						>
							<?php echo $this->svg_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
					</div>
				</div>

				<?php if ( $secondary_nav ) : ?>
					<nav class="ngt-hdr-secondary-nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'noghte-smart-headers' ); ?>">
						<?php echo $secondary_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</nav>
				<?php endif; ?>
			</div>

			<div id="<?php echo esc_attr( $instance_id . '-drawer' ); ?>" class="ngt-hdr-drawer" aria-hidden="true" data-ngt-drawer>
				<button type="button" class="ngt-hdr-drawer-backdrop" aria-label="<?php esc_attr_e( 'بستن منو', 'noghte-smart-headers' ); ?>" data-ngt-menu-close></button>
				<div class="ngt-hdr-drawer-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'منوی سایت', 'noghte-smart-headers' ); ?>" tabindex="-1" data-ngt-layer-panel>
					<div class="ngt-hdr-drawer-head">
						<div class="ngt-hdr-drawer-logo"><?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<button id="<?php echo esc_attr( $instance_id . '-drawer-close' ); ?>" type="button" class="ngt-hdr-icon-button ngt-hdr-close-button ngt-hdr-drawer-close-button" aria-label="<?php esc_attr_e( 'بستن منو', 'noghte-smart-headers' ); ?>" data-ngt-menu-close>
							<?php echo $this->svg_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
					</div>
					<nav class="ngt-hdr-mobile-navigation" aria-label="<?php esc_attr_e( 'منوی موبایل', 'noghte-smart-headers' ); ?>">
						<?php echo $mobile_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</nav>
					<div class="ngt-hdr-drawer-footer">
						<?php echo $this->get_cta_html( $settings, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</div>

			<?php echo $this->get_search_modal_html( $settings, $instance_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</header>
		<?php
		return (string) ob_get_clean();
	}

	private function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}

		wp_enqueue_style( 'ngt-hdr-frontend' );
		wp_enqueue_script( 'ngt-hdr-frontend' );
		$this->assets_enqueued = true;
	}

	private function sanitize_settings( $settings ) {
		$defaults = array(
			'style'                => '1',
			'menu'                 => '',
			'logo_id'              => 0,
			'logo_url'             => '',
			'logo_alt'             => '',
			'logo_width'           => 142,
			'mobile_logo_width'    => 118,
			'search'               => 'yes',
			'search_type'          => 'all',
			'cart'                 => 'yes',
			'account'              => 'yes',
			'mobile_search'        => 'yes',
			'mobile_cart'          => 'yes',
			'mobile_account'       => 'yes',
			'cta_text'             => '',
			'cta_url'              => '',
			'cta_target'           => '_self',
			'sticky'               => 'no',
			'transparent'          => 'no',
			'full_width'           => 'no',
			'container_width'      => 1280,
			'height'               => 78,
			'mobile_height'        => 64,
			'mobile_breakpoint'    => 1024,
			'icon_size'            => 21,
			'mobile_icon_size'     => 20,
			'background'           => '#ffffff',
			'text_color'           => '#15171a',
			'muted_color'          => '#68707a',
			'accent_color'         => '#2f6f58',
			'border_color'         => '#e9ecef',
			'icon_color'           => '#15171a',
			'search_icon_color'    => '',
			'menu_icon_color'      => '',
			'close_icon_color'     => '',
			'icon_hover_color'     => '#2f6f58',
			'custom_class'         => '',
		);

		$settings = wp_parse_args( (array) $settings, $defaults );
		$style    = (string) $settings['style'];
		if ( ! in_array( $style, array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '10' ), true ) ) {
			$style = '1';
		}

		$search_type = (string) $settings['search_type'];
		if ( ! in_array( $search_type, array( 'all', 'post', 'product' ), true ) ) {
			$search_type = 'all';
		}

		$icon_color        = $this->sanitize_color( $settings['icon_color'], '#15171a' );
		$search_icon_color = $this->sanitize_optional_color( $settings['search_icon_color'] );
		$menu_icon_color   = $this->sanitize_optional_color( $settings['menu_icon_color'] );
		$close_icon_color  = $this->sanitize_optional_color( $settings['close_icon_color'] );

		return array(
			'style'                => $style,
			'menu'                 => sanitize_text_field( (string) $settings['menu'] ),
			'logo_id'              => absint( $settings['logo_id'] ),
			'logo_url'             => esc_url_raw( (string) $settings['logo_url'] ),
			'logo_alt'             => sanitize_text_field( (string) $settings['logo_alt'] ),
			'logo_width'           => $this->clamp( $settings['logo_width'], 50, 320, 142 ),
			'mobile_logo_width'    => $this->clamp( $settings['mobile_logo_width'], 50, 240, 118 ),
			'search'               => $this->yes_no( $settings['search'] ),
			'search_type'          => $search_type,
			'cart'                 => $this->yes_no( $settings['cart'] ),
			'account'              => $this->yes_no( $settings['account'] ),
			'mobile_search'        => $this->yes_no( $settings['mobile_search'] ),
			'mobile_cart'          => $this->yes_no( $settings['mobile_cart'] ),
			'mobile_account'       => $this->yes_no( $settings['mobile_account'] ),
			'cta_text'             => sanitize_text_field( (string) $settings['cta_text'] ),
			'cta_url'              => esc_url_raw( (string) $settings['cta_url'] ),
			'cta_target'           => '_blank' === $settings['cta_target'] ? '_blank' : '_self',
			'sticky'               => $this->yes_no( $settings['sticky'] ),
			'transparent'          => $this->yes_no( $settings['transparent'] ),
			'full_width'           => $this->yes_no( $settings['full_width'] ),
			'container_width'      => $this->clamp( $settings['container_width'], 720, 1920, 1280 ),
			'height'               => $this->clamp( $settings['height'], 56, 140, 78 ),
			'mobile_height'        => $this->clamp( $settings['mobile_height'], 54, 100, 64 ),
			'mobile_breakpoint'    => (int) $this->clamp( $settings['mobile_breakpoint'], 640, 1280, 1024 ),
			'icon_size'            => $this->clamp( $settings['icon_size'], 15, 34, 21 ),
			'mobile_icon_size'     => $this->clamp( $settings['mobile_icon_size'], 15, 30, 20 ),
			'background'           => $this->sanitize_color( $settings['background'], '#ffffff' ),
			'text_color'           => $this->sanitize_color( $settings['text_color'], '#15171a' ),
			'muted_color'          => $this->sanitize_color( $settings['muted_color'], '#68707a' ),
			'accent_color'         => $this->sanitize_color( $settings['accent_color'], '#2f6f58' ),
			'border_color'         => $this->sanitize_color( $settings['border_color'], '#e9ecef' ),
			'icon_color'           => $icon_color,
			'search_icon_color'    => $search_icon_color ? $search_icon_color : $icon_color,
			'menu_icon_color'      => $menu_icon_color ? $menu_icon_color : $icon_color,
			'close_icon_color'     => $close_icon_color ? $close_icon_color : $icon_color,
			'icon_hover_color'     => $this->sanitize_color( $settings['icon_hover_color'], '#2f6f58' ),
			'custom_class'         => $this->sanitize_classes( $settings['custom_class'] ),
		);
	}

	private function get_wrapper_classes( $settings ) {
		$classes = array(
			'ngt-hdr',
			'ngt-hdr-layout-' . $settings['style'],
			'is-rtl-' . ( is_rtl() ? 'yes' : 'no' ),
			'mobile-search-' . ( 'yes' === $settings['mobile_search'] ? 'yes' : 'no' ),
			'mobile-cart-' . ( 'yes' === $settings['mobile_cart'] ? 'yes' : 'no' ),
			'mobile-account-' . ( 'yes' === $settings['mobile_account'] ? 'yes' : 'no' ),
		);

		if ( 'yes' === $settings['sticky'] ) {
			$classes[] = 'is-sticky';
		}
		if ( 'yes' === $settings['transparent'] ) {
			$classes[] = 'is-transparent';
		}
		if ( 'yes' === $settings['full_width'] ) {
			$classes[] = 'is-full-width';
		}
		if ( $settings['custom_class'] ) {
			$classes[] = $settings['custom_class'];
		}

		return $classes;
	}

	private function get_css_variables( $settings ) {
		return implode(
			';',
			array(
				'--ngt-hdr-container:' . $settings['container_width'] . 'px',
				'--ngt-hdr-height:' . $settings['height'] . 'px',
				'--ngt-hdr-mobile-height:' . $settings['mobile_height'] . 'px',
				'--ngt-hdr-logo-width:' . $settings['logo_width'] . 'px',
				'--ngt-hdr-mobile-logo-width:' . $settings['mobile_logo_width'] . 'px',
				'--ngt-hdr-icon-size:' . $settings['icon_size'] . 'px',
				'--ngt-hdr-mobile-icon-size:' . $settings['mobile_icon_size'] . 'px',
				'--ngt-hdr-bg:' . $settings['background'],
				'--ngt-hdr-text:' . $settings['text_color'],
				'--ngt-hdr-muted:' . $settings['muted_color'],
				'--ngt-hdr-accent:' . $settings['accent_color'],
				'--ngt-hdr-border:' . $settings['border_color'],
				'--ngt-hdr-icon:' . $settings['icon_color'],
				'--ngt-hdr-search-icon:' . $settings['search_icon_color'],
				'--ngt-hdr-menu-icon:' . $settings['menu_icon_color'],
				'--ngt-hdr-close-icon:' . $settings['close_icon_color'],
				'--ngt-hdr-icon-hover:' . $settings['icon_hover_color'],
			)
		);
	}

	private function get_responsive_css( $settings, $instance_id ) {
		$id         = '#' . $instance_id;
		$breakpoint = (int) $settings['mobile_breakpoint'];

		$css  = $id . ' .ngt-hdr-search-trigger,' . $id . ' .ngt-hdr-search-trigger .ngt-hdr-svg-search{color:var(--ngt-hdr-search-icon)!important;}';
		$css .= $id . ' .ngt-hdr-menu-trigger,' . $id . ' .ngt-hdr-menu-trigger .ngt-hdr-svg-menu{color:var(--ngt-hdr-menu-icon)!important;}';
		$css .= $id . ' .ngt-hdr-search-close-button,' . $id . ' .ngt-hdr-search-close-button .ngt-hdr-svg-close,' . $id . ' .ngt-hdr-drawer-close-button,' . $id . ' .ngt-hdr-drawer-close-button .ngt-hdr-svg-close{color:var(--ngt-hdr-close-icon)!important;}';
		$css .= $id . ' .ngt-hdr-search-trigger:hover,' . $id . ' .ngt-hdr-search-trigger:focus-visible,' . $id . ' .ngt-hdr-menu-trigger:hover,' . $id . ' .ngt-hdr-menu-trigger:focus-visible,' . $id . ' .ngt-hdr-search-close-button:hover,' . $id . ' .ngt-hdr-search-close-button:focus-visible,' . $id . ' .ngt-hdr-drawer-close-button:hover,' . $id . ' .ngt-hdr-drawer-close-button:focus-visible{color:var(--ngt-hdr-icon-hover)!important;}';
		$css .= $id . ' .ngt-hdr-search-trigger svg,' . $id . ' .ngt-hdr-search-trigger svg *,' . $id . ' .ngt-hdr-menu-trigger svg,' . $id . ' .ngt-hdr-menu-trigger svg *,' . $id . ' .ngt-hdr-search-close-button svg,' . $id . ' .ngt-hdr-search-close-button svg *,' . $id . ' .ngt-hdr-drawer-close-button svg,' . $id . ' .ngt-hdr-drawer-close-button svg *{stroke:currentColor!important;fill:none!important;}';

		$css .= '@media (max-width:' . $breakpoint . 'px){';
		$css .= $id . ' .ngt-hdr-main,' . $id . '.is-full-width .ngt-hdr-main{width:calc(100% - 24px);min-height:var(--ngt-hdr-mobile-height);grid-template-columns:minmax(0,1fr) auto;gap:10px;padding-inline:0;}';
		$css .= $id . ' .ngt-hdr-brand,' . $id . '.ngt-hdr-layout-2 .ngt-hdr-brand,' . $id . '.ngt-hdr-layout-5 .ngt-hdr-brand,' . $id . '.ngt-hdr-layout-7 .ngt-hdr-brand,' . $id . '.ngt-hdr-layout-8 .ngt-hdr-brand{grid-column:1;grid-row:1;justify-self:start;min-width:0;}';
		$css .= $id . ' .ngt-hdr-navigation,' . $id . ' .ngt-hdr-secondary-nav{display:none!important;}';
		$css .= $id . ' .ngt-hdr-actions,' . $id . '.ngt-hdr-layout-2 .ngt-hdr-actions,' . $id . '.ngt-hdr-layout-5 .ngt-hdr-actions,' . $id . '.ngt-hdr-layout-7 .ngt-hdr-actions,' . $id . '.ngt-hdr-layout-8 .ngt-hdr-actions{grid-column:2;grid-row:1;justify-self:end;gap:3px;min-width:0;}';
		$css .= $id . ' .ngt-hdr-logo-image{width:min(var(--ngt-hdr-mobile-logo-width),100%);max-width:min(var(--ngt-hdr-mobile-logo-width),34vw);max-height:calc(var(--ngt-hdr-mobile-height) - 14px);}';
		$css .= $id . ' .ngt-hdr-icon-button{width:38px;height:38px;border-radius:11px;}';
		$css .= $id . ' .ngt-hdr-icon-button svg{width:var(--ngt-hdr-mobile-icon-size);height:var(--ngt-hdr-mobile-icon-size);}';
		$css .= $id . ' .ngt-hdr-cta:not(.ngt-hdr-cta-drawer){display:none;}';
		$css .= $id . ' .ngt-hdr-cta-drawer{display:inline-flex;}';
		$css .= $id . ' .ngt-hdr-menu-toggle{display:inline-flex;}';
		$css .= $id . '.mobile-search-no .ngt-hdr-search-button{display:none;}';
		$css .= $id . '.mobile-cart-no .ngt-hdr-cart-button{display:none;}';
		$css .= $id . '.mobile-account-no .ngt-hdr-account-button{display:none;}';
		$css .= $id . '.ngt-hdr-layout-4{padding-block-start:10px;padding-inline:10px;}';
		$css .= $id . '.ngt-hdr-layout-4 .ngt-hdr-shell,' . $id . '.ngt-hdr-layout-6 .ngt-hdr-main,' . $id . '.ngt-hdr-layout-9 .ngt-hdr-main{border-radius:18px;}';
		$css .= $id . '.ngt-hdr-layout-6,' . $id . '.ngt-hdr-layout-9{padding-inline:10px;}';
		$css .= $id . '.ngt-hdr-layout-6 .ngt-hdr-main,' . $id . '.ngt-hdr-layout-9 .ngt-hdr-main{width:100%;padding-inline:12px;}';
		$css .= '}';

		$css .= '@media (max-width:600px){';
		$css .= $id . ' .ngt-hdr-main,' . $id . '.is-full-width .ngt-hdr-main{width:calc(100% - 16px);gap:5px;}';
		$css .= $id . ' .ngt-hdr-actions{gap:1px;}';
		$css .= $id . ' .ngt-hdr-icon-button{width:35px;height:35px;border-radius:10px;}';
		$css .= $id . ' .ngt-hdr-cart-count{inset-block-start:0;inset-inline-end:0;}';
		$css .= $id . ' .ngt-hdr-drawer-panel{width:min(calc(100vw - 18px),390px);padding:18px;}';
		$css .= $id . ' .ngt-hdr-search-form{grid-template-columns:1fr;}';
		$css .= $id . ' .ngt-hdr-search-submit{width:100%;}';
		$css .= $id . ' .ngt-hdr-search-dialog{border-radius:19px;padding:20px;}';
		$css .= $id . ' .ngt-hdr-search-head{margin-block-end:18px;}';
		$css .= $id . ' .ngt-hdr-site-title{max-width:34vw;overflow:hidden;text-overflow:ellipsis;}';
		$css .= '}';

		return $css;
	}

	private function get_logo_html( $settings ) {
		$alt  = $settings['logo_alt'] ? $settings['logo_alt'] : get_bloginfo( 'name' );
		$html = '';

		if ( $settings['logo_id'] ) {
			$html = wp_get_attachment_image(
				$settings['logo_id'],
				'full',
				false,
				array(
					'class'         => 'ngt-hdr-logo-image',
					'alt'           => $alt,
					'loading'       => 'eager',
					'fetchpriority' => 'high',
				)
			);
		} elseif ( $settings['logo_url'] ) {
			$html = '<img class="ngt-hdr-logo-image" src="' . esc_url( $settings['logo_url'] ) . '" alt="' . esc_attr( $alt ) . '" loading="eager" fetchpriority="high">';
		} else {
			$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) {
				$html = wp_get_attachment_image(
					$custom_logo_id,
					'full',
					false,
					array(
						'class'         => 'ngt-hdr-logo-image',
						'alt'           => $alt,
						'loading'       => 'eager',
						'fetchpriority' => 'high',
					)
				);
			}
		}

		if ( ! $html ) {
			$html = '<span class="ngt-hdr-site-title">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		}

		return '<a class="ngt-hdr-logo-link" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . $html . '</a>';
	}

	private function get_menu_html( $menu, $menu_id, $menu_class ) {
		$args = array(
			'container'    => false,
			'menu_id'      => sanitize_html_class( $menu_id ),
			'menu_class'   => $menu_class,
			'echo'         => false,
			'fallback_cb'  => array( $this, 'fallback_menu' ),
			'depth'        => 3,
			'items_wrap'   => '<ul id="%1$s" class="%2$s">%3$s</ul>',
			'item_spacing' => 'discard',
		);

		if ( $menu ) {
			$locations = get_nav_menu_locations();
			if ( isset( $locations[ $menu ] ) ) {
				$args['theme_location'] = $menu;
			} else {
				$args['menu'] = $menu;
			}
		} elseif ( has_nav_menu( 'primary' ) ) {
			$args['theme_location'] = 'primary';
		}

		$html = wp_nav_menu( $args );
		$html = is_string( $html ) ? $html : '';

		// Desktop and mobile copies must not contain duplicate element IDs.
		$html = preg_replace( '/\s+id=("|\')menu-item-[^"\']+("|\')/i', '', $html );
		return $html;
	}

	public function fallback_menu( $args ) {
		$pages = wp_list_pages(
			array(
				'title_li' => '',
				'echo'     => false,
				'depth'    => 2,
			)
		);

		if ( ! $pages ) {
			$pages = '<li class="menu-item"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'خانه', 'noghte-smart-headers' ) . '</a></li>';
		}

		return '<ul id="' . esc_attr( $args->menu_id ) . '" class="' . esc_attr( $args->menu_class ) . '">' . $pages . '</ul>';
	}

	private function get_cta_html( $settings, $drawer = false ) {
		if ( ! $settings['cta_text'] || ! $settings['cta_url'] ) {
			return '';
		}

		$class = $drawer ? 'ngt-hdr-cta ngt-hdr-cta-drawer' : 'ngt-hdr-cta';
		$rel   = '_blank' === $settings['cta_target'] ? ' rel="noopener noreferrer"' : '';

		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $settings['cta_url'] ) . '" target="' . esc_attr( $settings['cta_target'] ) . '"' . $rel . '>' . esc_html( $settings['cta_text'] ) . '</a>';
	}

	private function get_search_button_html( $settings, $instance_id ) {
		if ( 'yes' !== $settings['search'] ) {
			return '';
		}

		return '<button id="' . esc_attr( $instance_id . '-search-trigger' ) . '" type="button" class="ngt-hdr-icon-button ngt-hdr-search-button ngt-hdr-search-trigger" aria-label="' . esc_attr__( 'جست‌وجو', 'noghte-smart-headers' ) . '" aria-controls="' . esc_attr( $instance_id . '-search' ) . '" aria-expanded="false" data-ngt-search-open>' . $this->svg_icon( 'search' ) . '</button>';
	}

	private function get_account_html( $settings ) {
		if ( 'yes' !== $settings['account'] ) {
			return '';
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
		} elseif ( is_user_logged_in() ) {
			$url = get_edit_profile_url();
		} else {
			$url = wp_login_url( home_url( '/' ) );
		}

		return '<a class="ngt-hdr-icon-button ngt-hdr-account-button" href="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'حساب کاربری', 'noghte-smart-headers' ) . '">' . $this->svg_icon( 'user' ) . '</a>';
	}

	private function get_cart_html( $settings ) {
		if ( 'yes' !== $settings['cart'] || ! function_exists( 'wc_get_cart_url' ) ) {
			return '';
		}

		$count = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$count = (int) WC()->cart->get_cart_contents_count();
		}

		return '<a class="ngt-hdr-icon-button ngt-hdr-cart-button" href="' . esc_url( wc_get_cart_url() ) . '" aria-label="' . esc_attr__( 'سبد خرید', 'noghte-smart-headers' ) . '">' . $this->svg_icon( 'bag' ) . '<span class="ngt-hdr-cart-count" aria-hidden="true">' . esc_html( $count ) . '</span></a>';
	}

	private function get_search_modal_html( $settings, $instance_id ) {
		if ( 'yes' !== $settings['search'] ) {
			return '';
		}

		$hidden_post_type = '';
		$placeholder      = esc_attr__( 'جست‌وجو در سایت…', 'noghte-smart-headers' );
		if ( 'product' === $settings['search_type'] && post_type_exists( 'product' ) ) {
			$hidden_post_type = '<input type="hidden" name="post_type" value="product">';
			$placeholder      = esc_attr__( 'جست‌وجوی محصولات…', 'noghte-smart-headers' );
		} elseif ( 'post' === $settings['search_type'] ) {
			$hidden_post_type = '<input type="hidden" name="post_type" value="post">';
			$placeholder      = esc_attr__( 'جست‌وجوی نوشته‌ها…', 'noghte-smart-headers' );
		}

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id . '-search' ); ?>" class="ngt-hdr-search-modal" aria-hidden="true" data-ngt-search-modal>
			<button type="button" class="ngt-hdr-search-backdrop" aria-label="<?php esc_attr_e( 'بستن جست‌وجو', 'noghte-smart-headers' ); ?>" data-ngt-search-close></button>
			<div class="ngt-hdr-search-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $instance_id . '-search-title' ); ?>" tabindex="-1" data-ngt-layer-panel>
				<div class="ngt-hdr-search-head">
					<h2 id="<?php echo esc_attr( $instance_id . '-search-title' ); ?>"><?php esc_html_e( 'دنبال چه چیزی هستید؟', 'noghte-smart-headers' ); ?></h2>
					<button id="<?php echo esc_attr( $instance_id . '-search-close' ); ?>" type="button" class="ngt-hdr-icon-button ngt-hdr-close-button ngt-hdr-search-close-button" aria-label="<?php esc_attr_e( 'بستن جست‌وجو', 'noghte-smart-headers' ); ?>" data-ngt-search-close>
						<?php echo $this->svg_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				</div>
				<form class="ngt-hdr-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id . '-search-input' ); ?>"><?php esc_html_e( 'عبارت جست‌وجو', 'noghte-smart-headers' ); ?></label>
					<input id="<?php echo esc_attr( $instance_id . '-search-input' ); ?>" class="ngt-hdr-search-input" type="search" name="s" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" required data-ngt-search-input>
					<?php echo $hidden_post_type; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button class="ngt-hdr-search-submit" type="submit">
						<?php echo $this->svg_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><?php esc_html_e( 'جست‌وجو', 'noghte-smart-headers' ); ?></span>
					</button>
				</form>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function svg_icon( $name ) {
		$icons = array(
			'menu'   => '<svg class="ngt-hdr-svg-icon ngt-hdr-svg-menu" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor"/></svg>',
			'close'  => '<svg class="ngt-hdr-svg-icon ngt-hdr-svg-close" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor"/></svg>',
			'search' => '<svg class="ngt-hdr-svg-icon ngt-hdr-svg-search" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor"/><path d="M16 16l4 4" fill="none" stroke="currentColor"/></svg>',
			'user'   => '<svg class="ngt-hdr-svg-icon ngt-hdr-svg-user" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="3.5" fill="none" stroke="currentColor"/><path d="M5 20c.8-4 3.1-6 7-6s6.2 2 7 6" fill="none" stroke="currentColor"/></svg>',
			'bag'    => '<svg class="ngt-hdr-svg-icon ngt-hdr-svg-bag" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor"><path d="M5 8.5h14l-1 11H6l-1-11Z" fill="none" stroke="currentColor"/><path d="M9 9V6.5a3 3 0 0 1 6 0V9" fill="none" stroke="currentColor"/></svg>',
		);

		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
	}

	private function yes_no( $value ) {
		return in_array( strtolower( (string) $value ), array( 'yes', '1', 'true', 'on' ), true ) ? 'yes' : 'no';
	}

	private function clamp( $value, $min, $max, $default ) {
		$value = is_numeric( $value ) ? (float) $value : (float) $default;
		return max( $min, min( $max, $value ) );
	}

	private function sanitize_color( $value, $default ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(rgb|rgba|hsl|hsla)\([0-9.,%\s-]+\)$/i', $value ) ) {
			return $value;
		}
		return $default;
	}

	private function sanitize_optional_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		return $this->sanitize_color( $value, '' );
	}

	private function sanitize_classes( $value ) {
		$classes = preg_split( '/\s+/', trim( (string) $value ) );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
		return implode( ' ', $classes );
	}
}
