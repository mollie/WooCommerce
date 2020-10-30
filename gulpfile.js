const gulp = require('gulp')
const { series, parallel, src, dest } = gulp
const del = require('del')
const pump = require('pump')
const minimist = require('minimist')
const { spawn } = require('child_process')
const usage = require('gulp-help-doc')
const zip = require('gulp-zip')
const rename = require('gulp-rename')
const date = require('date-and-time')

const options = {
    ...minimist(
        process.argv,
        {
            string: [
                'packageVersion',
                'packageName',
                'baseDir',
                'buildDir',
                'distDir',
            ],
            bools: [
                'q',
            ],
            default: {
                packageVersion: 'dev',
                packageName: 'mollie-payments-for-woocommerce',
                baseDir: __dirname,
                buildDir: `${__dirname}/build`,
                distDir: `${__dirname}/dist`,
                q: false
            },
        }
    )
}


// --------------------------------------------------------------------
// CONSTANTS
// --------------------------------------------------------------------


// --------------------------------------------------------------------
// FUNCTIONS
// --------------------------------------------------------------------
let log = (function (options) {

    /**
     * Logs text.
     *
     * @param text The text to lot.
     */
    let out = function (text) {
        if (!options.q) {
            console.log(text);
        }
    };

    let err = function (text) {
        console.error(text);
    }

    /**
     * @alias out()
     */
    let log = function (text) {
        return out(text);
    };

    log.out = out;
    log.err = err;

    return log;
})(options);

let exec = (function (options) {
    /**
     * @param {string} cmd The command to run.
     * @param {Array<string>} args A list of arguments to run the command with
     * @param {Object} settings Any settings for the child process.
     * @param {Function<Function>[]} tasks The tasks to chain.
     */
    return function (cmd, args, settings, cb) {
        args = args || []
        settings = settings || {}
        cb = cb || function () {}

        let fullCmd = cmd + (args ? ' ' + args.join(' ') : '');
        log(`exec: ${fullCmd}`);
        let stdout = ''
        let stderr = ''
        let error = null;
        let ps = spawn(cmd, args, settings);

        if (!options.q) {
            ps.stdout.pipe(process.stdout)
        }

        ps.stderr.on('data', (data) => {
            stderr += data.toString()
        })

        ps.stdout.on('data', (data) => {
            stdout += data.toString()
        })

        ps.on('error', (err) => {
            err = err.toString()
            error = new Error(err);
            cb(error, stdout, stderr);
        });

        ps.on('exit', (code) => {
            if (code) {
                error = new Error(`Subprocess exited with code ${code}\n${stderr}`);
            }

            cb(error, stdout, stderr);
        });

        return ps
    }
})(options)

/**
 * @param {Function<Function>[]} tasks The tasks to chain.
 * @param {Function} callback The callback that should run at the end of the chain.
 */
let chain = function (tasks, callback) {
    let task = tasks.shift()

    return task((error) => {
        if (error || !tasks.length) {
            return callback(error)
        }

        return chain(tasks, callback)
    })
}

// --------------------------------------------------------------------
// TASKS
// --------------------------------------------------------------------

function _help() {
    return function help(done) {
        return usage(gulp)
    }
}

function _clean({baseDir, buildDir}) {
    return function clean(done) {
        del.sync([buildDir], {force: true, cwd: baseDir})
        done()
    }
}

function _copy({baseDir, buildDir, distDir}) {
    return function copy(done) {
        pump(
            src([`**/*.*`, `!${buildDir}/**/*.*`, `!${distDir}/**/*.*`, '!.git/**/*.*', '!vendor/**/*.*', '!node_modules/**/*.*'], {base: baseDir, cwd: baseDir, dot: true}),
            dest(buildDir),
            done
        )
    }
}

function _installPhp({buildDir}) {
    return function installPhp(done) {
        chain([
            (done) => { return exec('composer', ['config', 'platform.php', '5.6.39'], {}, done)},
            (done) => { return exec(`composer`, ['install', '--prefer-dist', '--optimize-autoloader', '--no-dev'], {cwd: buildDir}, done) },
        ], done);
    }
}

function _installJs({buildDir}) {
    return function installJs(done) {
        chain([
            (done) => { return exec('npm', ['install', '--production'], {cwd: buildDir}, done) },
        ], done);
    }
}

function _processAssets({baseDir, buildDir}) {
    return function processAssets(done) {
        exec(
            `node_modules/.bin/encore`,
            ['production', `--env.basePath="${buildDir}"`],
            {cwd: baseDir},
            done,
        )
    }
}

function _archive({baseDir, buildDir, distDir, packageVersion, packageName}) {
    return function archive(done) {
        return new Promise(() =>
        {
            exec(
                `git log -n 1 | head -n 1 | sed -e 's/^commit //' | head -c 8`,
                [],
                {'shell': true},
                (error, stdout) =>
                {
                    if (error) {
                        done(new Error(error));
                    }

                    let commit = stdout;
                    let timestamp =  date.format(new Date(), 'YYYY-MM-DD.HH-mm-ss', true)

                    pump(
                        gulp.src([
                            'inc/**/*.*',
                            'languages/**/*.*',
                            'public/**/*.*',
                            'src/**/*.*',
                            'vendor/**/*.*',
                            'node_modules/**/*.*',
                            '!node_modules/.package.lock.json',
                            'license.txt',
                            'mollie-payments-for-woocommerce.php',

                            // Cleanup
                            '!**/README',
                            '!**/readme',
                            '!**/README.md',
                            '!**/readme.md',
                            '!**/readme.txt',
                            '!**/readme.txt',
                            '!**/DEVELOPERS',
                            '!**/developers',
                            '!**/DEVELOPERS.md',
                            '!**/developers.md',
                            '!**/DEVELOPERS.txt',
                            '!**/developers.txt',
                            '!**/composer.json',
                            '!**/composer.lock',
                            '!**/package.json',
                            '!**/package-lock.json',
                            '!**/yarn.lock',
                            '!**/phpunit.xml.dist',
                            '!**/webpack.config.js',
                            '!**/.github',
                            '!**/.git',
                            '!**/.gitignore',
                            '!**/.gitattributes',
                            '!**/Makefile',
                            '!**/bitbucket-pipelines.yml',
                            '!**/bin.yml',
                            '!**/test.yml',
                            '!**/tests.yml',
                        ], {
                            base: buildDir,
                            cwd: buildDir,
                            dot: true,
                        }),
                        rename((path) => { path.dirname = `${packageName}/` + path.dirname }),
                        zip(`${packageName}_${packageVersion}+${commit}.${timestamp}.zip`),
                        gulp.dest(distDir),
                        done,
                    )
                }
            )
        })
    }
}

// --------------------------------------------------------------------
// TARGETS
// --------------------------------------------------------------------
exports.help = series(
    _help(options),
)

exports.clean = series(
    _clean(options),
)

exports.copy = series(
    _copy(options)
)

exports.installPhp = series(
    _installPhp(options)
)

exports.installJs = series(
    _installJs(options)
)

exports.processAssets = series(
    _processAssets(options)
)

exports.archive = series(
    _archive(options)
)

exports.install = parallel(
    exports.installJs,
    exports.installPhp,
)

exports.process = series(
    exports.processAssets,
)

exports.build = series(
    exports.clean,
    exports.copy,
    exports.install,
    exports.process,
)

exports.dist = series(
    exports.build,
    exports.archive,
)

exports.default = series(
    exports.build
)
