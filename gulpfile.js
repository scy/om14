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
};

gulp.task("clean", function (done) {
	gulp.src(opts.clean, { read: false })
		.pipe(clean());
	done();
});

gulp.task("assets", function () {
	gulp.src(opts.imgSrc)
		.pipe(gulp.dest(opts.imgDest));
	gulp.src(opts.fontSrc)
		.pipe(gulp.dest(opts.fontDest));
	gulp.src(opts.favSrc)
		.pipe(gulp.dest(opts.docroot));
});

gulp.task("html", function () {
	var data = {}, tplFile = fs.readFileSync(opts.template, "UTF-8");
	gulp.src(opts.pages)
		.pipe(marked())
		.pipe(cheerio(function ($, done) {
			var $h1 = $("h1"); // all <h1> elements
			data.title = $h1.first().text(); // set the title to the text of the first <h1>
			// Add a wrapper span inside every <h1>.
			$h1.each(function (idx, el) {
				var $el = $(el);
				$el.html('<span class="wrapper">' + $el.html() + '</span>');
			});
			done();
		}))
		.pipe(ssg({}))
		.pipe(through.obj(function (file, enc, cb) {
			if (file.isBuffer) {
				data.meta = file.meta;
				file.contents = new Buffer(template(tplFile, {
					contents: file.contents,
					data: data
				}));
			}
			this.push(file);
			return cb();
		}))
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
