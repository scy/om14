var gulp     = require("gulp")
  , cheerio  = require("gulp-cheerio")
  , clean    = require("gulp-clean")
  , concat   = require("gulp-concat")
  , fs       = require("fs")
  , http     = require("http")
  , marked   = require("gulp-marked")
  , sass     = require("gulp-sass")
  , ssg      = require("gulp-ssg")
  , template = require("lodash").template
  , through  = require("through2")
  , uglify   = require("gulp-uglify")
  ;

var files = {
	docroot: "env/%/htdocs",
	src: "src/site",
	build: "build/_all",
	pages: ">pages/**/*.md",
	template: ">tpl/om14.html",
	scssSrc: ">scss/om14.scss",
	scssBuild: "*/css",
	scssDest: "/",
	headJSSrc: ">js/*modernizr*",
	headJSBuild: "*/headjs",
	headJSDest: "/",
	footJSSrc: ">js/!(*modernizr*)",
	footJSBuild: "*/footjs",
	footJSDest: "/",
	favSrc: ">favicons/**",
	fontSrc: ">fonts/**",
	fontDest: "/fonts",
	imgSrc: ">img/**",
	imgDest: "/img",
	htaccessSrc: ">.htaccess"
};

var piwikIDs = {
	stage: 1,
	live: 2
};

var file = function (key, env) {
	if (!key in files) {
		throw new Error("no such file key: " + key);
	}
	return files[key]
		.replace(/^\//, files.docroot + "/")
		.replace(/^>/, files.src + "/")
		.replace(/^\*/, files.build + "/")
		.replace(/%/g, typeof env === "string" ? env : "_unknown")
		;
};

var allfiles = function (env) {
	var obj = {};
	for (var key in files) {
		obj[key] = file(key, env);
	}
	return obj;
};

var envtask = function (name, a, b) {
	var deps = [], func = function () {};
	if (typeof b === "undefined") {
		if (typeof a === "function") {
			func = a;
		} else {
			deps = a;
		}
	} else {
		deps = a; func = b;
	}
	var envs = ["stage", "live"], depcollect = [];
	envs.forEach(function (env) {
		var taskname = env + (name === "" ? "" : ("-" + name)), thisobj = allfiles(env);
		var envdeps = deps.map(function (dep) {
			return dep.replace(/^\*-/, env + "-");
		});
		thisobj.env = env;
		thisobj.file = function (key) {
			return file(key, env);
		};
		gulp.task(taskname, envdeps, (function (that) {
			return function () {
				return func.apply(that);
			};
		})(thisobj));
		depcollect.push(taskname);
	});
	gulp.task(name === "" ? "all" : name, depcollect);
};

var ts = new Date().getTime();

gulp.task("clean", function () {
	return gulp.src([ "build", "env" ], { read: false })
		.pipe(clean());
});

envtask("images", function () {
	return gulp.src(this.imgSrc)
		.pipe(gulp.dest(this.imgDest));
});

envtask("fonts", function () {
	return gulp.src(this.fontSrc)
		.pipe(gulp.dest(this.fontDest));
});

envtask("favicons", function () {
	return gulp.src(this.favSrc)
		.pipe(gulp.dest(this.docroot));
});

envtask("htaccess", function () {
	return gulp.src(this.htaccessSrc)
		.pipe(gulp.dest(this.docroot));
});

envtask("assets", [ "*-images", "*-fonts", "*-favicons", "*-htaccess" ]);

envtask("html", function () {
	var env = this.env, page = {}, tplFile = fs.readFileSync(this.template, "UTF-8");
	return gulp.src(this.pages)
		.pipe(marked())
		.pipe(ssg({}))
		.pipe(through.obj(function (file, enc, cb) {
			if (file.isBuffer) {
				page = {
					name: file.meta.name,
					isHome: file.meta.isHome,
					isIndex: file.meta.isIndex,
					url: file.meta.url,
					ts: ts,
					env: env,
					piwikID: piwikIDs[env]
				};
				file.contents = new Buffer(template(tplFile, {
					contents: file.contents,
					page: page
				}));
			}
			this.push(file);
			return cb();
		}))
		.pipe(cheerio(function ($, done) {
			var $h1 = $("h1"); // all <h1> elements
			page.title = $h1.first().text(); // text of the first <h1>
			// Set title tag.
			var $title = $("title").first();
			$title.text(page.title + " – " + $title.text());
			// Add a wrapper span inside every <h1>.
			$h1.each(function (idx, el) {
				var $el = $(el);
				$el.html('<span class="wrapper">' + $el.html() + '</span>');
			});
			// Set Piwik <noscript> fallback image URL.
			$("noscript img").attr("src", "https://stats.openmind-konferenz.de/piwik/piwik.php?idsite="
					+ page.piwikID + "&rec=1&_cvar="
					+ encodeURIComponent(JSON.stringify({
						1: ["hasJS", "no"],
						2: ["hasCanvas", "no"]
					}))
			);
			// Insert timestamp into some resources for cachebusting. Will be rewritten by .htaccess.
			$('script[src], link[rel="stylesheet"]').each(function (idx, el) {
				var $el = $(el), attr = el.name == "script" ? "src" : "href";
				var url = $el.attr(attr);
				url = url.replace(/^(\/[^/]+)(\.(?:js|css))$/, "$1." + ts + "$2");
				$el.attr(attr, url);
			});
			// Make "page" available as JS variable on the page itself.
			$("#pageinfo").html("window.pageinfo = " + JSON.stringify(page) + ";");
			done();
		}))
		.pipe(gulp.dest(this.docroot));
});

gulp.task("build-css", function () {
	return gulp.src(file("scssSrc"))
		.pipe(sass({
			outputStyle: "compressed"
		}))
		.pipe(gulp.dest(file("scssBuild")));
});

envtask("css", [ "build-css" ], function () {
	return gulp.src(this.scssBuild + "/**")
		.pipe(gulp.dest(this.scssDest));
});

["head", "foot"].forEach(function (part) {
	gulp.task("build-" + part + "js", function () {
		return gulp.src(file(part + "JSSrc"))
			.pipe(uglify())
			.pipe(concat("om14-" + part + ".js", { newLine: ";" }))
			.pipe(gulp.dest(file(part + "JSBuild")));
	});

	envtask(part + "js", [ "build-" + part + "js" ], function () {
		return gulp.src(this[part + "JSBuild"] + "/**")
			.pipe(gulp.dest(this[part + "JSDest"]));
	});
});

envtask("js", [ "*-headjs", "*-footjs" ]);

envtask("", [ "*-assets", "*-html", "*-css", "*-js" ]);

gulp.task("watch", function () {
	var files = allfiles("stage");
	var cbmatch = /^(\/[^\/]+\.)\d+\.(js|css)$/, cbreplace = "$1$2";
	var stat = new (require("node-static").Server)(files.docroot, { cache: false });
	require("http").createServer(function (req, res) {
		req.addListener("end", function () {
			stat.serve(req, res, function (e) {
				if (e) {
					if (e.status === 404 && req.url.match(cbmatch)) {
						stat.serveFile(req.url.replace(cbmatch, cbreplace), 200, {}, req, res);
					} else {
						res.writeHead(e.status, e.headers);
						res.end();
					}
				}
			});
		}).resume();
	}).listen(8014);
	gulp.watch([ files.imgSrc ], [ "stage-images" ]);
	gulp.watch([ files.fontSrc ], [ "stage-fonts" ]);
	gulp.watch([ files.favSrc ], [ "stage-favicons" ]);
	gulp.watch([ files.htaccessSrc ], [ "stage-htaccess" ]);
	gulp.watch([ files.pages, files.template ], [ "stage-html" ]);
	gulp.watch([ "src/site/scss/**" ], [ "stage-css", "stage-html" ]);
	gulp.watch([ files.headJSSrc ], [ "stage-headjs" ]);
	gulp.watch([ files.footJSSrc ], [ "stage-footjs" ]);
});

gulp.task("default", [ "stage", "watch" ]);
