var config = require('./webpack.config.js')
var aliases = Object.keys(config.resolve.alias).map(function (value) {
  return [value, config.resolve.alias[value]]
})
module.exports = {
  "parser": "babel-eslint",
  "env": {
    "browser": true,
    "commonjs": true,
    "es6": true,
    "node": true
  },
  "parserOptions": {
    "ecmaFeatures": {
      "jsx": true
    },
    "sourceType": "module"
  },
  "plugins": ["import"],
  "rules": {
    "semi": [
      "error",
      "never"
    ],
    "no-const-assign": "warn",
    "no-this-before-super": "warn",
    "no-undef": "warn",
    "no-unreachable": "warn",
    "no-unused-vars": "warn",
    "constructor-super": "warn",
    "valid-typeof": "warn",
    "prefer-const": "warn",

    "import/no-named-as-default": [
      0
    ],
    "import/extensions": [
      0
    ],
    "import/prefer-default-export": [
      0
    ],
    "import/no-absolute-path": [
      0
    ],
    "import/no-unresolved": [
      2,
      {
        "ignore": [
          "app", "component", "lib", "asset"
        ]
      }
    ],
    "import/no-extraneous-dependencies": [
      "error"
    ]
  },
  "settings": {
    "import/resolver": {
      "alias": aliases
    }
  }
}
