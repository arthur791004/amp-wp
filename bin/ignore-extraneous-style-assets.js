/**
 * Webpack plugin to prevent extraneous assets from being emitted for stylesheets.
 *
 * JS files and PHP asset files generated by `DependencyExtractionWebpackPlugin` are currently ignored.
 */
class IgnoreExtraneousStyleAssets {
	isCssFile( file ) {
		return /^(?!!!).+\.(?:sc|sa|c)ss$/.test( file );
	}

	generateIgnoreRegex( entries ) {
		const expression = entries
			.reduce( ( acc, entry ) => `${ acc }|${ entry }.js|${ entry }.asset.php`, '' )
			.substr( 1 );

		return new RegExp( expression );
	}

	apply( compiler ) {
		const cssEntries = [];

		compiler.hooks.compilation.tap( 'IgnoreExtraneousStyleAssets', ( compilation ) => {
			// The `addEntry` compilation hook is undocumented. As for why, ¯\_(ツ)_/¯.
			compilation.hooks.addEntry.tap( 'IgnoreExtraneousStyleAssets', ( entry, name ) => {
				if ( entry.type === 'single entry' ) {
					if ( this.isCssFile( entry.request ) ) {
						cssEntries.push( name );
					}
				} else if ( entry.type === 'multi entry' ) {
					const filteredDependencies = entry.dependencies.filter(
						( singleDependency ) => this.isCssFile( singleDependency.request ),
					);

					// If all dependencies of the entry are CSS files, add it to the list of entries to ignore.
					if ( entry.dependencies.length === filteredDependencies.length ) {
						cssEntries.push( name );
					}
				}
			} );
		} );

		compiler.hooks.emit.tap( 'IgnoreExtraneousStyleAssets', ( compilation ) => {
			if ( cssEntries.length === 0 ) {
				return;
			}

			const ignoreRegex = this.generateIgnoreRegex( cssEntries );

			Object.keys( compilation.assets ).forEach( ( assetName ) => {
				if ( ignoreRegex.test( assetName ) ) {
					delete compilation.assets[ assetName ];
				}
			} );
		} );
	}
}

module.exports = IgnoreExtraneousStyleAssets;
