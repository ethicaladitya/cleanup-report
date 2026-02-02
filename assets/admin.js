(function($){
  $(document).ready(function() {
    // Enable WP media picker for inputs with data-media-target
    $(document).on('click', '.crg-pick', function(e){
      e.preventDefault();
      
      // Check if wp.media is available
      if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        alert('WordPress media library is not loaded. Please refresh the page.');
        return;
      }
      
      var target = $(this).data('media-target'); // FIXED: use dash attribute instead of camelCase
      var frame = wp.media({ 
        title: 'Select image', 
        button: { text: 'Use this image' }, 
        multiple: false,
        library: { type: 'image' }
      });
      
      frame.on('select', function(){
        var attachment = frame.state().get('selection').first().toJSON();
        var targetInput = $('input[name="'+target+'"]');
        if (targetInput.length === 0) {
          // Handle screenshot array format
          targetInput = $('input[name*="'+target+'"]');
        }
        targetInput.val(attachment.url);
      });
      
      frame.open();
    });
    
    // Review workflow functionality
    $('.crg-review-action').on('click', function(e) {
      var action = $(this).data('action');
      var confirmation = true;
      
      if (action === 'approve') {
        confirmation = confirm('Are you sure you want to approve and publish this report?');
      } else if (action === 'return') {
        var notes = prompt('Please provide notes for the author (optional):');
        if (notes === null) return; // User cancelled
        $(this).closest('form').find('textarea[name="review_notes"]').val(notes);
      }
      
      if (confirmation) {
        $(this).closest('form').find('input[name="review_action"]').val(action);
        $(this).closest('form').submit();
      }
    });
    
    // Disable editing for submitted reports (for authors)
    if ($('.crg-review-status').hasClass('status-submitted') && !$('.crg-review-actions').length) {
      $('#post input, #post textarea, #post select').prop('disabled', true);
      $('#publish').prop('disabled', true).text('Submitted for Review');
    }
  });
})(jQuery);
