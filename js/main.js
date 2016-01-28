/* main.js - command line tool - takes in any number of files & uses docdust on them */

import * as dd from './docdust.js';
import atty from './atty.js';
import dusthelp from './dusthelp';
import async from 'async';
import fs from 'fs';
import _ from 'lodash';

function getMeta (fn) {
	var metadata = {};
	metadata.path = fn;

	var slash_idx = fn.lastIndexOf('/');
	if (slash_idx === -1) {
		slash_idx = fn.lastIndexOf('\\');
		if (slash_idx === -1) {
			console.log('no slash in ' + fn);
			return false;
		}
	}
	slash_idx++;
	metadata.dir = fn.substr(0, slash_idx);
	metadata.name = fn.substr(slash_idx);

	var ext_idx = metadata.name.lastIndexOf('.');
	if (ext_idx === -1) {
		console.log('no ext in ' + metadata.name);
		return false;
	}

	metadata.ext = metadata.name.substr(ext_idx + 1);
	metadata.name = metadata.name.substr(0, ext_idx);

	return metadata;
}

function getDateStr () {
	var d = new Date();

	var year = d.getFullYear();
	var month = '' + (d.getMonth() + 1);
	var day = '' + d.getDate();

	if (month.length < 2) month = '0' + month;
	if (day.length < 2) day = '0' + day;

	return year + month + day;
}

if (!process.argv[3]) {
	console.log('incorrect number of cli args. usage: babel-node ' +
	'index.js ./case.json ./template.odt');
} else {
	var caseMeta = getMeta(process.argv[2]);

	if (caseMeta === false) {
		console.log(new Error('case json read failed - no slash or wrong ext'));
	} else {
		var context = _.merge(atty, dusthelp);
		var datestr = getDateStr();
		fs.readFile(caseMeta.path, function (err, caseJSON) {
			if (err) console.log(err);
			else {
				_.merge(context, JSON.parse(caseJSON));

				// loop through the supplied template filenames
				var tempArr = [];
				tempArr = process.argv.splice(3, process.argv.length);
				async.each(tempArr, function (tempFile, cb) {
					var tempMeta = getMeta(tempFile);
					var newPath = caseMeta.dir + caseMeta.name + '.' + datestr + '.' + tempMeta.name + '.' + tempMeta.ext;
					fs.readFile(tempFile, function (readerr, buf) {
						if (readerr) cb('readerr:' + readerr);
						else {
							dd.duster(tempMeta.ext, buf, context,
								function (dusterr, newBuf) {
									if (dusterr) cb('dusterr:' + dusterr);
									else {
										fs.writeFile(newPath, newBuf, function (writeerr) {
											if (writeerr) cb('writeerr:' + writeerr);
											else {
												console.log(newPath + ' created.');
												cb();
											}
										});
									}
								});
							}
						});
					}, function (asyncerr) {
						if (asyncerr) console.log('asyncerr:' + asyncerr);
					});
				}
			});
		}
	}
