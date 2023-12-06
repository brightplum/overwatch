(function (Drupal) {
  'use strict';

  /**
   * Behavior for Overwatch System Data.
   */
  Drupal.behaviors.overwatchSystemData = {
    attach: function (context) {
      // Return if not the first attach.
      if (context !== document) {
        return;
      }

      console.log("Enter here");
    }
  };
})(Drupal);
