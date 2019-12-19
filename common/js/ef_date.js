jQuery(document).ready(function($) {
  var dateTimePicks = jQuery(".date-time-pick");

  dateTimePicks.each(function() {
    var $dTP = jQuery(this);

    $dTP.datetimepicker({
      dateFormat: "M dd yy",
      firstDay: ef_week_first_day,
      alwaysSetTime: false,
      controlType: "select",
      altField: '#' + $dTP.prop("id") + "_hidden",
      altFieldTimeOnly: false,
      altFormat: "yy-mm-dd",
      altTimeFormat: "HH:mm"
    });
  });

  jQuery(".date-pick").datepicker({
    dateFormat: "M dd yy",
    firstDay: ef_week_first_day
  });
});
