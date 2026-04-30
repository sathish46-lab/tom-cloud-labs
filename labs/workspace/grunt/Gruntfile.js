const sass = require("sass-embedded");
module.exports = function (grunt) {
  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),
    concat: {
      dist: {
        src: [
          // No ../ here because the Gruntfile is INSIDE the grunt folder
          "node_modules/@coreui/coreui/dist/js/coreui.bundle.min.js",
          "node_modules/simplebar/dist/simplebar.min.js",
          "node_modules/chart.js/dist/chart.umd.js",
          "node_modules/@coreui/chartjs/dist/js/coreui-chartjs.js",
          "node_modules/@coreui/utils/dist/umd/index.js",

          "../js/*.js",
          "../js/**/*.js",
        ],
        dest: "../../htdocs/js/app.js",
      },
    },

    uglify: {
      options: {
        mangle: false,
        compress: false,
        beautify: false,
      },
      build: {
        files: {
          "../../htdocs/js/app.min.js": ["../../htdocs/js/app.js"],
        },
      },
    },

    obfuscator: {
      options: {
        banner: "",
        debugProtection: true,
        debugProtectionInterval: true,
        controlFlowFlattening: true,
        controlFlowFlatteningThreshold: 1,
        disableConsoleOutput: true,
        mangle: true,
        selfDefending: true,
        domainLock: ["labs.tomweb.fun", "labsbeta.tomweb.fun"],
      },
      build: {
        files: {
          "../../htdocs/js/app.o.js": ["../../htdocs/js/app.js"],
        },
      },
    },

    sass: {
      options: {
        style: "expanded",
        sourceMap: false,
        implementation: sass,
        // This tells Sass to look inside the grunt/node_modules folder automatically
        loadPath: ["node_modules"],
      },
      dist: {
        files: {
          "../../htdocs/css/app.css": "../sass/app.scss",
        },
      },
    },
    copy: {
      main: {
        files: [
          {
            expand: true,
            flatten: true,
            src: ["node_modules/@coreui/icons/sprites/**"],
            dest: "../../htdocs/assets/icons/",
          },
        ],
      },
    },

    watch: {
      scripts: {
        files: [
          "Gruntfile.js",
          "../js/**/*.js", // Watches all JS files in any subfolder
        ],
        tasks: ["concat", "uglify:build", "obfuscator"], // Updates all 3 JS files on save
        options: {
          debounceDelay: 300,
        },
      },
      css: {
        files: [
          "../sass/**/*.scss", // Watches app.scss and all @use partials
          "../sass/**/*.sass",
        ],
        tasks: ["sass"],
        options: {
          debounceDelay: 300,
          spawn: false, // Keeps the process running faster
        },
      },
    },
  });

  grunt.loadNpmTasks("grunt-contrib-watch");
  grunt.loadNpmTasks("grunt-contrib-concat");
  grunt.loadNpmTasks("grunt-sass");
  grunt.loadNpmTasks("grunt-contrib-uglify");
  grunt.loadNpmTasks("grunt-contrib-obfuscator");
  grunt.loadNpmTasks("grunt-contrib-copy");

  // Custom task to strip source map references
  grunt.registerTask(
    "stripSourceMaps",
    "Remove source map references",
    function () {
      const fs = require("fs");
      const files = ["../../htdocs/js/app.js", "../../htdocs/js/app.min.js"];

      files.forEach(function (file) {
        try {
          let content = fs.readFileSync(file, "utf8");
          // Split by lines and remove source map references from each line
          const lines = content.split("\n");
          const cleanedLines = lines.map(function (line) {
            return line.replace(/\/\/# sourceMappingURL=[^\n]*/g, "");
          });
          const cleanedContent = cleanedLines.join("\n");
          fs.writeFileSync(file, cleanedContent, "utf8");
          grunt.log.ok("Stripped source maps from " + file);
        } catch (err) {
          if (err.code !== "ENOENT") {
            grunt.log.warn("Error processing " + file + ": " + err.message);
          }
        }
      });
    },
  );

  grunt.registerTask("default", [
    "copy",
    "sass:dist",
    "concat",
    "stripSourceMaps",
    "uglify",
    "watch",
  ]);
  grunt.registerTask("build", [
    "copy",
    "sass:dist",
    "concat",
    "stripSourceMaps",
    "uglify",
    "obfuscator",
  ]);
};
