(function($) {

    $(document).ready(function() {
        //var $formNotice = $('.woocommerce-checkout');
        var $imgForm    = $('.woocommerce-checkout');
        //var $imgNotice  = $imgForm.find('.image-notice');
        //var $imgPreview = $imgForm.find('.image-preview');
        var $imgFile    = $imgForm.find('#id_card_upload');
       // var $imgId      = $imgForm.find('[name="id_card_file"]');
        var $imgUrl      = $imgForm.find('[name="id_card"]');

        var success = '<p id="id-card-success" style="color: #12a058;">Successfully Uploaded</p>';

        $imgForm

        $imgForm.submit(function( event ) {
            payment_method = $("input[name='payment_method']:checked").val()

            imgUpload = $("input[name='id_card']").val();

            if(payment_method == 'wcpg-pay-on-credit' && imgUpload == ''){
                $('#upload_doc').addClass('woocommerce-invalid woocommerce-invalid-required-field')
            }
          });

        $imgFile.on('change', function(e) {
            e.preventDefault();
            jQuery('body').trigger('update_checkout');
        
            var formData = new FormData();

            $('#id-card-success').remove();
        
            formData.append('action', 'upload-attachment');
            formData.append('async-upload', $imgFile[0].files[0]);
            formData.append('name', $imgFile[0].files[0].name);
            formData.append('_wpnonce', nat_card_config.nonce);
        
            $.ajax({
                url: nat_card_config.upload_url,
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                type: 'POST',
                success: function(resp) {

                    $('#uploadComplete').after(success);
                   $($imgUrl).val(resp.data.url)
                    
                }
            });
        });        
    });
})(jQuery);