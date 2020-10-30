const gulp = require('gulp')
const gulpPhpUnit = require('gulp-phpunit')
const gulpZip = require('gulp-zip')
const gulpDel = require('del')
const minimist = require('minimist')
const fs = require('fs')
const pump = require('pump')
const usage = require('gulp-help-doc')
const { exec } = require('child_process')

const ENV_PRODUCTION = 'production'
const ENV_DEVELOPMENT = 'development'
const PACKAGE_NAME = 'mollie-payments-for-woocommerce'
const BASE_PATH = './'
const TMP_DESTINATION_PATH = './dist'
const PACKAGE_PATH = `${TMP_DESTINATION_PATH}/${PACKAGE_NAME}`

const options = minimist(
  process.argv.slice(3),
  {
    string: [
      'packageVersion',
      'compressPath',
    ],
    bools: [
        'q',
    ],
    default: {
      compressPath: process.compressPath || BASE_PATH,
      q: false
    },
  }
)

log = (function (options) {

    let out = function (text) {
        if (!options.q) {
            console.log(text);
        }
    };

    let err = function (text) {
        console.error(text);
    }

    let log = function (text) {
        return out(text);
    };

    log.out = out;
    log.err = err;

    return log;
})(options);

let exec = (function (options) {
    return function (cmd, args, settings, cb) {
        args = args || []
        settings = settings || {}
        cb = cb || function () {}

        let fullCmd = cmd + (args ? ' ' + args.join(' ') : '');
        log(`exec: ${fullCmd}`);
        let stdout = ''
        let stderr = ''
        let error = null;
        let ps = proc.spawn(cmd, args, settings);

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
 * @param {Function<Function>[]} tasks The tasks to chain
 * @param {Function} callback The function to run when all tasks complete
 */
chain = function (tasks, callback) {
    let task = tasks.shift()

    return task((error) => {
        if (error || !tasks.length) {
            return callback(error)
        }

        return chain(tasks, callback)
    })
}

function setupCheckPackageVersion ({ packageVersion })
{
  return function checkPackageVersion (done)
  {
    return new Promise((resolve, reject) =>
    {
      if (packageVersion) {
        done()
      }

      reject('Missing --packageVersion option with a semver value.')
    })
  }
}

function setupComposer ({ environment, basePath })
{
  let parameters = ''

  if (environment === ENV_PRODUCTION) {
    parameters = `--prefer-dist --optimize-autoloader --no-dev --working-dir=${basePath}`
  }

  return function composer (done)
  {
    return exec(
      `composer install ${parameters}`,
      (error, stdout, stderr) =>
      {
        if (error) {
          throw new Error(error)
        }

        done()
      },
    )
  }
}

function setupEncore ({ environment, basePath })
{
  return function encore (done)
  {
    environment = (environment === ENV_DEVELOPMENT) ? 'dev' : environment

    exec(
      `./node_modules/.bin/encore ${environment} --env.basePath ${basePath}`,
      (error, stdout, stderr) =>
      {
        if (error) {
          throw new Error(error)
        }

        done()
      },
    )
  }
}

function setupPhpunit ()
{
  return function phpunit (done)
  {
    return new Promise(() =>
    {
      pump(
        gulp.src('./phpunit.xml.dist'),
        gulpPhpUnit(
          './vendor/bin/phpunit',
          {
            debug: false,
            clear: false,
            notify: false,
            statusLine: false,
          },
        ),
        done,
      )
    })
  }
}

function setupCopyFiles ({ sources, destination, basePath })
{
  return function copyPackageFiles (done)
  {
    return new Promise(() =>
    {
      pump(
        gulp.src(
          sources,
          {
            base: basePath,
          }
        ),
        gulp.dest(destination),
        done,
      )
    })
  }
}

function deleteTemporaryFiles ()
{
  if (!fs.existsSync(TMP_DESTINATION_PATH)) {
    throw new Error(`Cannot create package, ${TMP_DESTINATION_PATH} doesn't exists.`)
  }

  gulpDel.sync(
    [
      `${PACKAGE_PATH}/public/css/entrypoints.json`,
      `${PACKAGE_PATH}/public/css/manifest.json`,
      `${PACKAGE_PATH}/public/css/runtime.js`,
      `${PACKAGE_PATH}/public/js/entrypoints.json`,
      `${PACKAGE_PATH}/public/js/runtime.js`,
      `${PACKAGE_PATH}/public/js/manifest.json`,
      `${TMP_DESTINATION_PATH}/**/.gitignore`,
      `${TMP_DESTINATION_PATH}/**/.gitattributes`,
      `${TMP_DESTINATION_PATH}/**/.travis.yml`,
      `${TMP_DESTINATION_PATH}/**/.scrutinizer.yml`,
      `${TMP_DESTINATION_PATH}/**/.gitattributes`,
      `${TMP_DESTINATION_PATH}/**/.git`,
      `${TMP_DESTINATION_PATH}/**/changelog.txt`,
      `${TMP_DESTINATION_PATH}/**/changelog.md`,
      `${TMP_DESTINATION_PATH}/**/CHANGELOG.md`,
      `${TMP_DESTINATION_PATH}/**/CHANGELOG`,
      `${TMP_DESTINATION_PATH}/**/README`,
      `${TMP_DESTINATION_PATH}/**/README.md`,
      `${TMP_DESTINATION_PATH}/**/readme.md`,
      `${TMP_DESTINATION_PATH}/**/readme.txt`,
      `${TMP_DESTINATION_PATH}/**/CONTRIBUTING.md`,
      `${TMP_DESTINATION_PATH}/**/CONTRIBUTING`,
      `${TMP_DESTINATION_PATH}/**/composer.json`,
      `${TMP_DESTINATION_PATH}/**/composer.lock`,
      `${TMP_DESTINATION_PATH}/**/phpcs.xml`,
      `${TMP_DESTINATION_PATH}/**/phpcs.xml.dist`,
      `${TMP_DESTINATION_PATH}/**/phpunit.xml`,
      `${TMP_DESTINATION_PATH}/**/phpunit.xml.dist`,
      `${TMP_DESTINATION_PATH}/**/bitbucket-pipelines.yml`,
      `${TMP_DESTINATION_PATH}/**/test`,
      `${TMP_DESTINATION_PATH}/**/tests`,
      `${TMP_DESTINATION_PATH}/**/bin`,
      `${TMP_DESTINATION_PATH}/**/Dockerfile`,
      `${TMP_DESTINATION_PATH}/**/Makefile`,
    ],
  )
}

function setupCompressPackage ({ packageVersion, compressPath, basePath })
{
  return function compressPackage (done)
  {
    const timeStamp = new Date().getTime()

    deleteTemporaryFiles()

    return new Promise(() =>
    {
      exec(
        `git log -n 1 | head -n 1 | sed -e 's/^commit //' | head -c 8`,
        {},
        (error, stdout) =>
        {
          const shortHash = error ? timeStamp : stdout

          pump(
            gulp.src(`${TMP_DESTINATION_PATH}/**/*`, {
              base: TMP_DESTINATION_PATH,
            }),
            gulpZip(`${PACKAGE_NAME}-${packageVersion}-${shortHash}.zip`),
            gulp.dest(
              compressPath,
              {
                base: TMP_DESTINATION_PATH,
                cwd: basePath,
              },
            ),
            done,
          )
        },
      )
    })
  }
}

function setupCleanDist ()
{
  return async function cleanDist ()
  {
    await gulpDel(TMP_DESTINATION_PATH)
  }
}

function help ()
{
  return usage(gulp)
}

const cleanDist = setupCleanDist()

const buildAssetsTask = gulp.series(
  setupEncore({
    ...options,
    basePath: BASE_PATH,
    environment: ENV_DEVELOPMENT
  }),
)

const testsTask = gulp.series(
  setupPhpunit(),
)

/**
 * Create the plugin package distribution.
 *
 * @task {dist}
 * @arg {packageVersion} Package version, the version must to be conformed to semver.
 * @arg {compressPath} Where the resulting package zip have to be stored.
 */
exports.dist = gulp.series(
  setupCheckPackageVersion({
    ...options,
    environment: ENV_PRODUCTION
  }),
  cleanDist,
  setupCopyFiles({
    ...options,
    environment: ENV_PRODUCTION,
    basePath: BASE_PATH,
    sources: [
      './public/**/*',
      '!./public/{css,css/**,js,js/**}',
      './inc/**/*',
      './src/**/*',
      './license.txt',
      './mollie-payments-for-woocommerce.php',
      './composer.json',
      './composer.lock',
      './languages/*'
    ],
    destination: PACKAGE_PATH,
  }),
  setupEncore({
    ...options,
    basePath: PACKAGE_PATH,
    environment: ENV_PRODUCTION
  }),
  setupComposer({
    ...options,
    basePath: PACKAGE_PATH,
    environment: ENV_PRODUCTION
  }),
  setupCompressPackage({
    ...options,
    environment: ENV_PRODUCTION
  }),
  cleanDist
)

/**
 * Setup the Development Environment, usually for the first time
 *
 * @task {setup}
 */
exports.setup = gulp.series(
  buildAssetsTask,
  setupComposer({ environment: ENV_DEVELOPMENT }),
)

exports.help = help
exports.default = help

/**
 * Build the assets for development
 *
 * @task {buildAssets}
 */
exports.buildAssets = buildAssetsTask

/**
 * Run Tests
 *
 * @task {tests}
 */
exports.tests = testsTask
