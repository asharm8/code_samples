(function ($, Drupal) {
  Drupal.behaviors.ViewAjaxAppliedFilter = {
    attach: function (context, settings) {

      // Each Filter
      $(context).find('.view-filters').each(function() {

        // Variables
        var filters = $(this),
            options = filters.find('option'),
            tabWrapper = $('<div class="view-filters-tabs"></div>'),
            tabContent = $('<div class="filters-tab-title">' + Drupal.t('Filters Applied:') + '<div class="clear-all-filters">' + Drupal.t('Clear All') + '</div></div>');

        // Add Tab Wrapper
        filters.append(tabWrapper.append(tabContent));

        // Each Option
        options.each(function(i,opt) {

          // Create Tab
          var tabHTML = $('<div class="filter-tab' + ($(opt).prop('selected') ? ' active' : '') + '">' + $(this).text() + '</div>');

          // Add Tab
          tabWrapper.append(tabHTML);

          // Click Tab
          tabHTML.click(function() {

            // De-Select & Trigger Change
            $(opt).prop('selected',false).change();

          }); // end click tab

          // Option Change
          $(this).change(function() {

            // Detect State
            if($(this).prop('selected')) {

              // Activate Tab
              tabHTML.addClass('active');

            } else {

              // Deactivate Tab
              tabHTML.removeClass('active');

            } // end detect state

            // Toggle Tabs
            toggle_tab_wrapper();

          }); // end option change

        }); // end each option

        // Clear All
        tabContent.find('.clear-all-filters').click(function() {

          // Hide Tabs
          tabWrapper.find('.filter-tab').removeClass('active');

          // Each Option
          filters.find('option').each(function() {

            // Deselect
            $(this).prop('selected',false);

          }); // end each option

          // Each Checkboxe
          filters.find('input[type="checkbox"]').each(function() {

            $(this).prop('checked',false);

          }); // end each checkbox

          // Submit AJAX Form
          options.first().change();

        }); // end clear all

        // Toggle Tabs After Setup
        toggle_tab_wrapper();

        // Toggle Active Filter View
        function toggle_tab_wrapper() {

          // Detect Active Children
          if(tabWrapper.children('.active').length) {

            // Show Active Filters
            tabWrapper.show();

          } else {

            // Hide Active Filters
            tabWrapper.hide();

          } // end detect active children

        } // end toggle_tab_wrapper()

      }); // end each filters

    }
  };
})(jQuery, Drupal);
