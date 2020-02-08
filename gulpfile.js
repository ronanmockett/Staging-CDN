const { src, dest, watch, series, parallel } = require('gulp');
const autoprefixer = require('autoprefixer');
const clean = require('gulp-clean');
const cssnano = require('cssnano');
const postcss = require('gulp-postcss');
const sass = require('gulp-sass');
const sourcemaps = require('gulp-sourcemaps');

const config = {
    admin_dist: 'admin/dist',
    admin_css_path: 'css-maps',
    admin_sass: 'admin/assets/sass/**/*.scss',
}

// Tasks
function compileSass() {
    return src(config.admin_sass)
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', handleCSSError))
        .pipe(postcss([autoprefixer(), cssnano()]))
        .pipe(sourcemaps.write(config.admin_css_path))
        .pipe(dest('./admin/dist/'));
}

function refreshingFiles() {
    return src(config.admin_dist, { read: false })
        .pipe(clean({ force: true }));
}

function watchFiles() {
    watch(config.admin_sass, compileSass);
}

// error handling
function handleCSSError(error) {
    console.log(error.message.toString());
    this.emit('end');
}

// gulp task function export
exports.default = series(
    parallel(compileSass),
    watchFiles
);
exports.watch = watchFiles;
exports.sass = compileSass;
exports.clean = refreshingFiles;