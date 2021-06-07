# Quizattemptexport

Automatically exports quiz attempts submitted by students into PDF files.

The files will be exported in two ways:
* Into the moodle file system, where they may be viewed/downloaded through the browser
* Into a directory within moodledata

To avoid performance impact when a large number of quiz attempts is submitted at the same time, the automatic export will be done by a cronjob in the background.

You may also manually export any quiz attempt within the system, using the user interface hooked into the quiz instance administration. This is also where you may view and download the files from your browser.

## Packaged binary
The plugin uses a packaged binary to perform the transformation, which might require the installation of some additional shared libraries.
The plugin tries to perform an environment check upon installation or upgrade and will display the missing shared libraries.

Additionally, the binary needs to be executable by the apache user. When the plugin is pulled directly from the GIT repository
the execute bit should be set automatically, in any other case the execute bit needs to be set manually.

Path to binary:
> local/quizattemptexport/vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64

## Exported files within moodledata
Please make also sure that there is a directory within moodledata where the plugin may write the exported files to. 
This directory needs to be set within the plugins settings page.

The files will be written into sub-directories of the configured directory, where the first level is the ID of the course and
the second level is the ID of the course module (the id used when clicking on the link to the quiz instance within the course)

## Logging
In case of problems during the export, the plugin will write log output into the file "quizattemptexport.log" within moodledata.