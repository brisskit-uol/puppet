{literal}

<script>
  var bk_search_widget = ` 
    <div id="bk_search_widget">
      <label for="bk_search_name">Input name to search for (or whatever)</label>
      <input type="text" name="bk_search_name" id="bk_search_name" />
      <button type="button" id="bk_search_button">Populate name</button>
    </div>
  `;

  jQuery( document ).ready(function() {
    // Insert our search widget
    jQuery('#contactDetails').prepend(bk_search_widget);

    // Copy the patient details to the contact
    jQuery('#bk_search_button').click(function () {
      var selected_name = jQuery('#bk_search_name').val();
      jQuery('#first_name').val(selected_name);
    });
  });
</script>

{/literal}
