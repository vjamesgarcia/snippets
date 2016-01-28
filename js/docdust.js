/* docdust.js - exports duster() - used to open document & run the dust templating library on it
 * currently supports odt, ods, gnumeric, abw, xml */

// dealing with zip files
import jsz from 'jszip';
import zlib from 'zlib';
import { pd } from 'pretty-data';

// dustjs helpers
import commonHelpers from 'common-dustjs-helpers';
var helpers = new commonHelpers.CommonDustjsHelpers();
import dust from 'dustjs-linkedin';
helpers.export_to(dust);
import 'dustjs-helpers';

export function duster (ext, tempBuf, context, cb) {
	switch (ext) {
		case 'odt':
		case 'ods':
			var zip = new jsz(tempBuf);
			var content = zip.file('content.xml').asText();
			content = pd.xml(content);
			var contentTemplate = dust.compile(content, 'content');
			dust.loadSource(contentTemplate);
			dust.render('content', context, function (err, out) {
				if (err) console.log("contenterr=" + err);
				else {
					zip.file('content.xml', out);
					var styles = zip.file('styles.xml').asText();
					styles = pd.xml(styles);
					var stylesTemplate = dust.compile(styles, 'styles');
					dust.loadSource(stylesTemplate);
					dust.render('styles', context, function (styleserr, stylesout) {
						if (styleserr) console.log("styleserr=" + styleserr);
						else {
							zip.file('styles.xml', stylesout);
							cb(null, zip.generate({type: 'nodebuffer'}));
						}
					});
				}
			});
			break;
		case 'gnumeric':
			zlib.gunzip(tempBuf, function (err, unzipped) {
				if (err) console.log(err);
				else {
					var xmlTemplate = dust.compile(unzipped.toString(), 'gnu');
					dust.loadSource(xmlTemplate);
					dust.render('gnu', context, function (dusterr, out) {
						if (dusterr) console.log(dusterr);
						else {
							zlib.gzip(out, function (gzerr, zipped) {
								if (gzerr) console.log(gzerr);
								else cb(null, zipped);
							});
						}
					});
				}
			});
			break;
		case 'abw':
		case 'xml':
			var xmlTemplate = dust.compile(tempBuf.toString(), 'xml');
			dust.loadSource(xmlTemplate);
			dust.render('xml', context, function (err, out) {
				if (err) console.log(err);
				else if (ext === 'abw') {
					tidyABW(out, function (tidyerr, tidyout) {
						if (tidyerr) console.log(tidyerr);
						else cb(null, tidyout);
					});
				} else cb(null, out);
			});
			break;
		default:
	}
}
