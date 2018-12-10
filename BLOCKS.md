# Block Editor Compatibility

This file describes steps to start developing Blocks for Edit Flow.
Currently, the following Edit Flow modules are compatible with Gutenberg Block Editor:

* Custom Status

### Setup

*Note:* This document assumes you have a working knowledge of modern JavaScript and its tooling, including: npm, Webpack, and, of course, React.

Prerequisites: `npm`, `yarn` (optionally).

From the plugin's folder run:

```
npm i
```

or

```
yarn
```

This should leave you with everything you need for development, including local copy of Webpack and webpack-cli.


## Anatomy of an Edit Flow block.

There are two parts for Block Editor compatibility implementation for each module.

#### PHP

**TL;DR;** check out
[Custom Status Module](modules/custom-status/custom-status.php) and its corresponding [Block Editor Compat](modules/custom-status/compat/block-editor.php) for the working example.

On the PHP side, in the module's folder create a `compat` sub-folder, and in it, create a file named `block-editor.php`.

That file has to contain the class ${EF_Module_Class_Name}_Block_Editor_Compat.
E.g. for the `Custom Status` Module, which class name is `EF_Custom_Status`, the compat class name has to be `EF_Custom_Status_Block_Editor_Compat`.


Here's a super contrived example of a fictional module:

`modules/fictional-module/fictional-module.php`:

```php
<?php
class EF_Fictional_Module {
  protected $compat_hooks = [
    'admin_enqueue_scripts' => 'module_admin_scripts'
  ];

  function my_modules_admin_enqueue_scripts_action() {
    // something-something, not compatible with Gutenberg
  }
}
```

`modules/fictional-module/compat/block-editor.php`:

```php
<?php
class EF_Fictional_Module_Block_Editor_Compat {
  // @see in "common/php/trait-block-editor-compatible.php
  use Block_Editor_Compatible;

  // Holds the reference to the module, so we can use the module's logic
  $ef_module;

  function module_admin_scripts() {
    $this->ef_module->do_something_with_module();
  }
}
```

**Important**

To avoid any sort of class inheritance Edit Flow compat files use a trait [Block_Editor_Compatible](common/php/trait-block-editor-compatible.php). Right now it only contains the constructor that's shared between compat modules, but in the future using traits approach might be more flexible.

##### How does it work?

Each Edit Flow module follows the same pattern: attaching the necessary hooks for actions and filters on instantiation.

We have modified the loader logic in the main Edit_Flow class to try to instantiate the Block_Editor_Compat for corresponding module.

This way the code for existing modules doesn't need to be modified, except adding the `protected $compat_hooks` property.

On the instantiation of the module's compat, we'll iterate over `$compat_hooks` and remove hooks registered by the module, and add ones coming from compat class.

#### JavaScript

##### Development

```npm run dev```

This will start Webpack and make it watch for changes.

##### Build for production

```npm run build```

This will generate optimized/minified production-ready files.


##### File Structure
