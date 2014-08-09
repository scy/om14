var gulp     = require("gulp")
  , cheerio  = require("gulp-cheerio")
  , clean    = require("gulp-clean")
  , concat   = require("gulp-concat")
  , fs       = require("fs")
  , gtpl     = require("gulp-template")
  , http     = require("http")
  , marked   = require("gulp-marked")
  , request  = require("request")
  , sass     = require("gulp-sass")
  , shell    = require("gulp-shell")
  , spawn    = require("gulp-spawn")
  , srcstr   = require("vinyl-source-stream")
  , ssg      = require("gulp-ssg")
  , template = require("lodash").template
  , through  = require("through2")
  , uglify   = require("gulp-uglify")
  ;

var files = {
	envroot: "env/%",
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
	shopPrvSrc: "src/shop/{{OM14,views}/**,config.yml,om14d.php}",
	shopPubSrc: "src/shop/web/{**,.htaccess}",
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

var om14cheerio = function (page) {
	return cheerio(function ($, done) {
		var $h1 = $("h1"); // all <h1> elements
		if (!page.title) {
			page.title = $h1.first().text(); // text of the first <h1>
		}
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
		if (page.jsTitle) {
			page.title = page.jsTitle;
			delete page.jsTitle;
		}
		$("#pageinfo").html("window.pageinfo = " + JSON.stringify(page) + ";");
		done();
	});
};

envtask("html", function () {
	var env = this.env, page = {}, tplFile = fs.readFileSync(this.template, "UTF-8");
	return gulp.src(this.pages)
		.pipe(marked())
		.pipe(ssg({}))
		.pipe(through.obj(function (file, enc, cb) {
			var meta = file.meta;
			if (file.isBuffer) {
				page = {
					name: meta.name,
					isHome: meta.isHome,
					isIndex: meta.isIndex,
					url: meta.url,
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
		.pipe(om14cheerio(page))
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

gulp.task("install-composer", function () {
	return request("https://getcomposer.org/installer", function (err) {
		if (err) {
			throw err;
		}
	})
		.pipe(srcstr("composer-installer.php"))
		.pipe(spawn({
			cmd: "php",
			args: [
			      "--",
			      "--install-dir=src/shop"
			]
		}));
});

gulp.task("install-php-deps", shell.task([
	"./composer.phar install"
], {
	cwd: "src/shop"
}))

envtask("shop-template", function () {
	var page = {
		name: "shop",
		isHome: false,
		isIndex: false,
		url: "/shop/",
		ts: ts,
		env: this.env,
		piwikID: piwikIDs[this.env],
		title: "{% if title is defined %}{{ title|escape }} – {% endif %}Shop",
		jsTitle: "{% if title is defined %}{{ title|escape('js') }} – {% endif %}Shop"
	};
	return gulp.src(this.template)
		.pipe(gtpl({
			contents: "{% block content %}{% endblock %}",
			page: page
		}))
		.pipe(om14cheerio(page))
		.pipe(concat("om14.twig")) // collapsing the one-file input set
		.pipe(gulp.dest(this.envroot + "/views"));
});

envtask("shop-vendor", function () {
	return gulp.src("src/shop/vendor/**", {base: "src/shop"})
		.pipe(gulp.dest(this.envroot));
});

envtask("shop-private", function () {
	return gulp.src(this.shopPrvSrc, {base: "src/shop"})
		.pipe(gulp.dest(this.envroot));
});

envtask("shop-public", function () {
	return gulp.src(this.shopPubSrc, {base: "src/shop/web"})
		.pipe(gulp.dest(this.docroot + "/shop"));
});

envtask("shop", [ "*-shop-vendor", "*-shop-private", "*-shop-template", "*-shop-public" ]);

envtask("", [ "*-assets", "*-html", "*-css", "*-js", "*-shop" ]);

var watch = function () {
	var files = allfiles("stage");
	var cbmatch = /^(\/[^\/]+\.)\d+\.(js|css)$/, cbreplace = "$1$2";
	var stat = new (require("node-static").Server)(files.docroot, { cache: false });
	http.createServer(function (req, res) {
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
	gulp.watch([ files.headJSSrc ], [ "stage-headjs", "stage-html" ]);
	gulp.watch([ files.footJSSrc ], [ "stage-footjs", "stage-html" ]);
	gulp.watch([ files.shopPrvSrc ], [ "stage-shop-private" ]);
	gulp.watch([ files.shopPubSrc ], [ "stage-shop-public" ]);
};
gulp.task("watch", watch);
gulp.task("clean-watch", [ "clean" ], watch);

gulp.task("default", [ "stage", "watch" ]);
