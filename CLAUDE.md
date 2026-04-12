# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a personal website for Jonathan Roman, served as a PHP/HTML site. It consists of a single entry point (`default.php`) and supporting files in the `Files/` directory.

## Structure

- `default.php` — Main landing page. Uses PHP only for injecting the owner's name and page title into HTML. The layout is a two-column flex design (left half reserved for a canvas sketch, right half for name/links).
- `Files/rect.html` + `Files/rect.js` — A standalone p5.js generative art sketch (recursive rectangle subdivision with a blue color palette). The sketch uses `#sketch-container` as its canvas parent.
- `Files/resume.html` — Resume exported from Microsoft Word as HTML.

## Development

There is no build step, package manager, or test suite. This is a static PHP site intended to be served directly by a PHP-capable web server (e.g., Apache, nginx with PHP-FPM, or PHP's built-in server).

To preview locally:
```bash
php -S localhost:8000
```

Then open `http://localhost:8000/default.php`.

## Key Notes

- The `rect.js` sketch expects a DOM element with `id="sketch-container"` — `rect.html` does not currently provide this element (it has no `<div id="sketch-container">`), so the canvas attachment will fail unless that div is added.
- Links to `Files/Resume.html`, LinkedIn, and Goodreads in `default.php` are currently commented out.
- The `<script src="sketch.js">` in `default.php` is also commented out; `rect.js` is the active sketch file but lives only in `Files/`.
