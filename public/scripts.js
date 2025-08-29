jQuery(document).ready(function($) {
    $("#player_name").autocomplete({
        source: ajaxurl + '?action=hockeysignin_search_players',
        minLength: 2, // Start searching from two characters
        select: function(event, ui) {
            $('#player_name').val(ui.item.label); // Set the full name in the input when selected
            return false; // Prevent the value part from being inserted into the input
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>")
            .append("<div>" + item.label + "</div>")
            .appendTo(ul);
    };
});
