Install Gulp
- Open the terminal and from project, root navigate to sub-theme directory cd /web/themes/custom/overwatch_bp
- Verify that a directory called: 'css' exists

- Run:
--- lando npm install -g gulp
--- lando npm install sass gulp-sass gulp-concat gulp-clean-css gulp-uglify --save-dev
--- lando npm install --save-dev gulp-strip-css-comments

You'll need to run ´lando npm install gulp´ in the theme folder.

Compiling SCSS to CSS
- lando gulp

For developers while generating styles:
- lando gulp watch
- Start theming
- Add the end, finish it with ctrl|cmd C