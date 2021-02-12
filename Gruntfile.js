/**
 * @package     omeka-amazon-s3-storage-adapter
 * @copyright   King's College London Department of Digital Humanities
 * @license     https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

module.exports = function(grunt) {

  grunt.loadNpmTasks('grunt-contrib-compress');

  var pkg = grunt.file.readJSON('package.json');

  grunt.initConfig({

    compress: {

      dist: {
        options: {
          archive: 'pkg/omeka-amazon-s3-storage-adapter-'+pkg.version+'.zip'
        },
        dest: 'AmazonS3StorageAdapter/',
        src: [

          '**',

          // GIT
          '!.git/**',
          '!.gitignore',

          // NPM
          '!package.json',
          '!package-lock.json',
          '!node_modules/**',

          // GRUNT
          '!.grunt/**',
          '!Gruntfile.js',

          // DIST
          '!pkg/**',

          // Editor settings
          '!*.vim',
          '!.idea',
          '!*.iml',
        ]
      }

    }

  });

  // Spawn release package.
  grunt.registerTask('package', [
    'compress'
  ]);
};
