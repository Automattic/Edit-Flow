# Block Editor Compatibility

This file describes steps to start developing Blocks for Edit Flow.
Currently, the following Edit Flow modules are compatible with Gutenberg Block Editor:

* Custom Statuses

### Setup

*Note:* This document assumes you have a working knowledge of modern JavaScript and its tooling. Including npm, Webpack, and React.

Prerequisites: `npm`.

From the plugin's folder run:

```
npm i
```

This should leave you with everything you need for development, including a local copy of Webpack and webpack-cli.

## Anatomy of an Edit Flow block.

There are two parts for adding Block Editor compatibility to modules.

#### PHP

On the PHP side, we mostly just need to make sure the block assets are enqueued when they are needed. There is a [Block_Editor_Compatible](common/php/trait-block-editor-compatible.php) trait that gives access to helpful methods related to the block editor when used within modules.

#### JavaScript

##### Development

To start the Webpack in watch mode:

```npm run dev```

##### Build for production

To generate optimized/minified production-ready files:

```npm run build```

##### File Structure

```
blocks/
  # Source files:
  src/
    module-slug/
      block.js # Gutenberg Block code for the module
      editor.scss # Editor styles
      style.scss # Front-end styles
  # Build
  dist/
    module-slug.build.js # Built block js
    module-slug.editor.build.css # Built editor CSS
    module-slug.style.build.css # Built front-end CSS
```

The files from `dist/` should be enqueued in the compat class for the module.
