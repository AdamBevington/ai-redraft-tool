document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('ai-redraft-form');
    const resultDiv = document.getElementById('ai-redraft-result');
    const saveButton = document.getElementById('ai-save-post');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const postId = document.getElementById('post_id').value;
        const prompt = document.getElementById('prompt').value;
        const style = document.getElementById('style').value;

        resultDiv.innerHTML = '<p>Loading...</p>';

        fetch(aiRedraft.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action: 'ai_redraft_request',
                post_id: postId,
                prompt: prompt,
                style: style,
                _ajax_nonce: aiRedraft.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const aiContent = data.data.ai;
                const originalContent = data.data.original;

                // Create diff using the diff library
                const diff = Diff.createTwoFilesPatch('Original', 'AI Redraft', originalContent, aiContent);

                // Display the diff in the page
                resultDiv.innerHTML = '<h3>AI Redraft Diff:</h3><pre>' + diff + '</pre>';

                // Show the save button
                saveButton.style.display = 'inline-block';

                // Store the AI content and post ID for saving
                form.dataset.redraft = aiContent;
                form.dataset.postId = postId;
            } else {
                resultDiv.innerHTML = '<p>Error: ' + data.data + '</p>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<p>Unexpected error occurred.</p>';
            console.error('Error:', error);
        });
    });

    // Handle Save Post button click
    saveButton.addEventListener('click', function() {
        const redraft = form.dataset.redraft;
        const postId = form.dataset.postId;

        resultDiv.innerHTML += '<p>Saving...</p>';

        fetch(aiRedraft.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action: 'ai_save_post',
                post_id: postId,
                redraft: redraft,
                _ajax_nonce: aiRedraft.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML += '<p style="color: green;">Post updated successfully!</p>';
            } else {
                resultDiv.innerHTML += '<p style="color: red;">Error: ' + data.data + '</p>';
            }
        });
    });
});
