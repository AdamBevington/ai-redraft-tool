jQuery(document).ready(function($) {
    $('#ai_redraft_button').on('click', function() {
        var content;
        // Classic Editor
        if ($('#content').length) {
            content = $('#content').val();
        } 
        // Gutenberg Block Editor
        else if (typeof wp !== 'undefined' && wp.data) {
            content = wp.data.select('core/editor').getEditedPostContent();
        } else {
            $('#ai_redraft_result').html('<div style="color:red;">Could not detect editor.</div>');
            return;
        }

        var prompt = $('#ai_redraft_prompt').val();
        var style = $('#ai_redraft_style').val();

        $('#ai_redraft_result').html('<em>Redrafting... Please wait.</em>');

        $.post(aiRedraft.ajax_url, {
            action: 'ai_redraft_request',
            nonce: aiRedraft.nonce,
            content: content,
            prompt: prompt,
            style: style
        }, function(response) {
            if (response.success) {
                $('#ai_redraft_result').html(
                    '<textarea id="ai_redraft_output" style="width:100%;min-height:150px;">' + 
                    response.data.result + '</textarea>'
                );
                $('#ai_replace_content').show();
            } else {
                $('#ai_redraft_result').html(
                    '<span style="color:red;">Error: ' + 
                    (response.data && response.data.error ? response.data.error : 'Unknown error') + 
                    '</span>'
                );
            }
        });
    });

    $('#ai_replace_content').on('click', function() {
        var ai_content = $('#ai_redraft_output').val();

        // Gutenberg: Use blocks API if available and blocks API is present
        if (
            typeof wp !== 'undefined' &&
            wp.data &&
            wp.blocks &&
            wp.data.dispatch('core/block-editor').resetBlocks
        ) {
            // If not valid HTML, wrap in paragraphs
            if (!/^<([a-z][a-z0-9]*)\b[^>]*>/i.test(ai_content.trim())) {
                ai_content = '<p>' + ai_content.trim().replace(/\n+/g, '</p><p>') + '</p>';
            }
            // Parse HTML into blocks
            const blocks = wp.blocks.parse(ai_content);
            // Replace all blocks in the editor
            wp.data.dispatch('core/block-editor').resetBlocks(blocks);
            $('#ai_redraft_result').html('<span style="color:green;">Block editor post content replaced!</span>');
        }
        // Classic Editor textarea
        else if ($('#content').length) {
            $('#content').val(ai_content);
            $('#ai_redraft_result').html('<span style="color:green;">Classic editor post content replaced!</span>');
        }
        else {
            $('#ai_redraft_result').html('<span style="color:red;">Could not update post content (no compatible editor found).</span>');
        }

        $(this).hide();
    });
});
