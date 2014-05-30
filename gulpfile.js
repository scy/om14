var gulp     = require("gulp")
  , ecstatic = require("ecstatic")
  , cheerio  = require("gulp-cheerio")
  , clean    = require("gulp-clean")
  , concat   = require("gulp-concat")
  , http     = require("http")
  , marked   = require("gulp-marked")
  , sass     = require("gulp-sass")
  , ssg      = require("gulp-ssg")
  , uglify   = require("gulp-uglify")
  , wrap     = require("gulp-wrap")
  ;

var opts = {
    docroot:  "htdocs"
  , clean:    [ "htdocs/!(.gitignore)" ]
  , pages:    "src/site/pages/**/*.md"
  , template: "src/site/tpl/om14.html"
  , scss:     "src/site/scss/om14.scss"
  , allSCSS:  "src/site/scss/**"
  , headJS:   "src/site/js/*modernizr*"
  , footJS:   "src/site/js/*jquery*"
  , allJS:    "src/site/js/**"
};

var site = {
    title: "openmind #om14"
};

gulp.task("clean", function () {
	gulp.src(opts.clean, { read: false })
		.pipe(clean());
});

gulp.task("assets", function () {
	// TODO: implement
});

gulp.task("html", function () {
	var data = {};
	gulp.src(opts.pages)
		.pipe(marked())
		.pipe(cheerio(function ($, done) {
			data.title = $("h1").first().text();
			done();
		}))
		.pipe(wrap({ src: opts.template }, data))
		.pipe(ssg(site))
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("css", function () {
	gulp.src(opts.scss)
		.pipe(sass({
			outputStyle: "compressed"
		}))
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("js", function () {
	gulp.src(opts.headJS)
		.pipe(uglify())
		.pipe(concat("om14-head.js", { newLine: ";" }))
		.pipe(gulp.dest(opts.docroot));
	gulp.src(opts.footJS)
		.pipe(uglify())
		.pipe(concat("om14-foot.js", { newLine: ";" }))
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("all", [ "html", "css", "js" ]);

gulp.task("watch", function () {
	http.createServer(ecstatic({
		root: opts.docroot,
		defaultExt: "html", // https://github.com/jesusabdullah/node-ecstatic/issues/108
		autoIndex: true
	})).listen(8014);
	gulp.watch([ opts.pages, opts.template ], [ "html" ]);
	gulp.watch([ opts.allSCSS ], [ "css" ]);
	gulp.watch([ opts.allJS ], [ "js" ]);
});

gulp.task("default", [ "all", "watch" ]);
