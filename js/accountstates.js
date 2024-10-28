jQuery(document).ready(function(){
    // Initialize Select2
    jQuery('#a2z_dhlexpress_state').select2();
    var dhlexpressStateValue = jQuery('#dhlexpress_state').val();

    // Function to update states based on the selected country
    function updateStates() {
        var countryCode = jQuery('#a2z_dhlexpress_country').val();
        jQuery('#a2z_dhlexpress_state').empty();

        // Filter states based on the selected country
        var states = states_list.Data.filter(function(state){
            return state.country === countryCode;
        });

        // Add states to the dropdown
        states.forEach(function(state){
            var stateCode = state.code.split('-')[1]; // Get the part after the hyphen
            jQuery('#a2z_dhlexpress_state').append('<option value="' + stateCode + '">' + state.name + '</option>');
        });
        
        // Show/hide the hidden input field based on the presence of states
        if (states.length == 0) {
           
            // Enable the hidden field
            jQuery("#dhlexpress_state").css("display", "block");
            jQuery("#a2z_dhlexpress_state").css("display", "none");
            jQuery('#a2z_dhlexpress_state').select2('destroy');
            // Replace the value (replace 'new_value' with the desired value)
            jQuery("#dhlexpress_state").val(countryCode);
        }
    
    }

    // Bind the updateStates function to the change event of the country dropdown
    jQuery('#a2z_dhlexpress_country').change(updateStates).change();
  
    // Set the selected state on page load
    if (dhlexpressStateValue !== '') {
        jQuery('#a2z_dhlexpress_state').val(dhlexpressStateValue);
    }
});