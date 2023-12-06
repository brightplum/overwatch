const gulp = require("gulp");
const sass = require("gulp-sass")(require("sass"));
const stripCssComments = require("gulp-strip-css-comments");
const concat = require('gulp-concat');
const cleanCss = require('gulp-clean-css');
const uglify = require('gulp-uglify');

const paths = {
  scss: {
    src: "./src/scss/*.scss",
    dest: "./dist/css",
    watch: "./src/scss/**/*.scss",
  },
  js: {
    src: "./src/js/*.js",
    dest: "./dist/js",
    watch: 'src/js/*.js'
  },
};

// Move the javascript files into our js folder
async function js() {
  return gulp.src([paths.js.src])
      .pipe(concat('main.min.js'))
      .pipe(uglify())
      .pipe(gulp.dest(paths.js.dest));
}

// Compile .scss source to .css
async function compileSass() {
  return gulp.src(paths.scss.src)
      .pipe(
          sass().on("error", sass.logError)
      )
      .pipe(stripCssComments())
      .pipe(cleanCss({compatibility: 'ie8'}))
      .pipe(concat('main.min.css'))
      .pipe(gulp.dest(paths.scss.dest));
}

function watch() {
  gulp.watch(
      paths.js.watch,
      js
  );
  gulp.watch(
      paths.scss.watch,
      compileSass
  );
}

const build = gulp.parallel(gulp.parallel(compileSass, js));

exports.default = build;
exports.watch = watch;