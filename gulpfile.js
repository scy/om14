var gulp     = require("gulp")
  , cheerio  = require("gulp-cheerio")
  , clean    = require("gulp-clean")
  , concat   = require("gulp-concat")
  , ecstatic = require("ecstatic")
  , fs       = require("fs")
  , http     = require("http")
  , marked   = require("gulp-marked")
  , sass     = require("gulp-sass")
  , ssg      = require("gulp-ssg")
  , template = require("lodash").template
  , through  = require("through2")
  , uglify   = require("gulp-uglify")
  ;

var opts = {
    docroot:  "htdocs"
  , clean:    [ "htdocs/!(.gitignore)" ]
  , pages:    "src/site/pages/**/*.md"
  , template: "src/site/tpl/om14.html"
  , scss:     "src/site/scss/om14.scss"
  , allSCSS:  "src/site/scss/**"
  , headJS:   "src/site/js/*modernizr*"
  , footJS:   "src/site/js/!(*modernizr*)"
  , allJS:    "src/site/js/**"
  , imgSrc:   "src/site/img/**"
  , imgDest:  "htdocs/img"
  , fontSrc:  "src/site/fonts/**"
  , fontDest: "htdocs/fonts"
  , favSrc:   "src/site/favicons/**"
  , piwikSrc: "http://stats.openmind-konferenz.de/piwik/piwik.js"
};

var ts = new Date().getTime();

gulp.task("clean", function () {
	return gulp.src(opts.clean, { read: false })
		.pipe(clean());
});

gulp.task("images", function () {
	return gulp.src(opts.imgSrc)
		.pipe(gulp.dest(opts.imgDest));
});

gulp.task("fonts", function () {
	return gulp.src(opts.fontSrc)
		.pipe(gulp.dest(opts.fontDest));
});

gulp.task("favicons", function () {
	return gulp.src(opts.favSrc)
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("assets", [ "images", "fonts", "favicons" ]);

gulp.task("html", function () {
	var page = {}, tplFile = fs.readFileSync(opts.template, "UTF-8");
	return gulp.src(opts.pages)
		.pipe(marked())
		.pipe(ssg({}))
		.pipe(through.obj(function (file, enc, cb) {
			if (file.isBuffer) {
				page = {
					name: file.meta.name,
					isHome: file.meta.isHome,
					isIndex: file.meta.isIndex,
					url: file.meta.url,
					ts: ts
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
			// Remove header if not on the homepage.
			if (!page.isHome) {
				$("#header").remove();
			}
			// Set Piwik <noscript> fallback image URL.
			$("noscript img").attr("src", "//stats.openmind-konferenz.de/piwik/piwik.php?idsite=1&rec=1&_cvar="
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
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("css", function () {
	return gulp.src(opts.scss)
		.pipe(sass({
			outputStyle: "compressed"
		}))
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("headjs", function () {
	return gulp.src(opts.headJS)
		.pipe(uglify())
		.pipe(concat("om14-head.js", { newLine: ";" }))
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("footjs", function () {
	return gulp.src(opts.footJS)
		.pipe(uglify())
		.pipe(concat("om14-foot.js", { newLine: ";" }))
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("js", [ "headjs", "footjs" ]);

gulp.task("all", [ "assets", "html", "css", "js" ]);

gulp.task("watch", function () {
	http.createServer(ecstatic({
		root: opts.docroot,
		defaultExt: "html", // https://github.com/jesusabdullah/node-ecstatic/issues/108
		autoIndex: true
	})).listen(8014);
	gulp.watch([ opts.imgSrc ], [ "assets" ]);
	gulp.watch([ opts.pages, opts.template ], [ "html" ]);
	gulp.watch([ opts.allSCSS ], [ "css" ]);
	gulp.watch([ opts.allJS ], [ "js" ]);
});

gulp.task("default", [ "all", "watch" ]);
