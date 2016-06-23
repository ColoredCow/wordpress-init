module.exports = function (grunt) {

    grunt.initConfig({

        pkg: grunt.file.readJSON('package.json'),

        uglify: {
            options: {
                banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'
            },
            build: {
                src: 'src/js/*.js',
                dest: 'main.js'
            }
        },

        sass: {
            dist: {
                options: {
                    style: 'compact'
                },
                files: {
                    'style.css': 'src/scss/style.scss',
                }
            }
        },

        cssmin: {
            options: {
                mergeIntoShorthands: false,
                roundingPrecision: -1,
            },
            target: {
                files: {
                    'style.css': 'style.css'
                }
            }
        },

        uncss: {
            dist: {
                options: {
                    stylesheets  : [ 'style.css' ],
                    ignoreSheets : [/fonts.googleapis/],
                    urls         : [],
                },
                files: {
                    'style.css': ['**/*.php']
                }
            }
        },

        watch: {
            scripts: {
                files: [
                    'src/js/*.js',
                    'src/scss/*.scss',
                ],
                tasks: ['default'],
                options: {
                    spawn: false,
                }
            }
        }

    });

    grunt.loadNpmTasks('grunt-contrib');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-uncss');

    grunt.registerTask('default', ['sass', 'cssmin', 'uncss:dist', 'uglify']);

};
