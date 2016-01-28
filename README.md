# Snippets

Files in this repo were taken from larger projects which aren't fully represented here. Furthermore these examples were developed by a single programmer unhindered by proper comment etiquette. ("I'll do it later when I need to share the project...") The code may be inscrutable as a result.

## JS

* Part of a command-line tool which applies the dustjs templating library to office docs. Rushed to "production" in an amalgamation of ES6 and regular gross javascript
  * main.js - uses docdust.js, among others, to work magic
  * docdust.js - said magic
* React entry points for isomorphic project (Not shown here: actions, components, routes, anything interesting.)
  * server.js - renders requested component server-side for quick page load and SEO
  * client.js - loads react on top of the server render

## PHP

6 out of approx 60 classes which compose a SaaS project, developed with PHP, MySQL and Apache before I was interested in such arcane concepts as scalability and server overhead. In some places, classes handle page rendering, form submissions and other tasks, for enigmatic reasons unknown to even myself.