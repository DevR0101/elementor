/* eslint-disable no-console */

import { execSync } from 'child_process';
export class UpgradeElementor {
	cmd( cmd: string ) {
		try {
			console.log( cmd );
			const text = execSync( cmd ).toString();
			console.log( text );
		} catch ( e ) {
			console.error( e.toString() );
		}
	}

	cleanUpBeforeTest() {
		if ( ! process.env.CI ) {
			this.cmd( 'rm -rf  plugin' );
			this.cmd( 'rm -rf  ../../../elementor' );
		}
	}

	getBuild() {
		// Don't forget to npx grunt build in root folder for local testing
		this.cmd( 'mkdir plugin' );
		this.cmd( 'cd ../../../ && mkdir elementor' );
		this.cmd( 'cd ../../../ && cp -r ./build/* elementor && zip -r elementor elementor' );
		this.cmd( 'cd ../../../ && mv ./elementor.zip ./tests/playwright/upgrade-test/plugin' );
	}

	runServer() {
		this.cmd( 'npx wp-env start' );
	}

	installPluginFromWP() {
		const version = process.env.ELEMENTOR_PLUGIN_VERSION;
		console.log( 'version is: ' + version );
		if ( version !== '' ) {
			this.cmd( `npx wp-env run cli bash -c 'wp plugin install elementor --version=${ version }  --activate --force'` );
		} else {
			this.cmd( `npx wp-env run cli wp plugin install elementor --activate` );
		}
		this.cmd( 'npx wp-env run cli wp plugin list' );
	}

	setupTests() {
		if ( ! process.env.CI ) {
			this.cmd( 'npm run test:setup' );
			this.cmd( 'npx wp-env run cli wp elementor experiments activate e_font_icon_svg,e_lazyload,e_optimized_assets_loading,e_optimized_css_loading,additional_custom_breakpoints,rating,e_image_loading_optimization' );
			this.cmd( 'cd ../../../ && npx playwright install chromium' );
		}
	}

	installCurrentPlugin() {
		this.cmd( `npx wp-env run cli wp plugin install ./plugin/elementor.zip --force` );
		this.cmd( 'npx wp-env run cli wp plugin list' );
	}

	runSmokeTest() {
		if ( ! process.env.CI ) {
			this.cmd( 'cd ../../../ && npm run test:playwright:elements-regression -- --grep="Test heading template"' );
		}
	}
}

const runner = new UpgradeElementor();
runner.cleanUpBeforeTest();
runner.getBuild();
runner.runServer();
runner.installPluginFromWP();
runner.installCurrentPlugin();
runner.setupTests();
runner.runSmokeTest();
