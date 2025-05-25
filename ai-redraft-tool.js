document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('ai-redraft-form');
    const resultDiv = document.getElementById('ai-redraft-result');
    const saveButton = document.getElementById('ai-save-post');

    if (!form) {
        console.error('AI Redraft form not found. Ensure the form ID is correct.');
        return;
    }

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

                let diffOutput = '';

                if (typeof Diff !== 'undefined' && Diff.createTwoFilesPatch) {
                    try {
                        diffOutput = Diff.createTwoFilesPatch('Original', 'AI Redraft', originalContent, aiContent);
                        diffOutput = `<pre>${diffOutput}</pre>`;
                    } catch (err) {
                        console.error('Diff error:', err);
                        diffOutput = '<p>Diff failed to generate. Displaying AI content only.</p><pre>' + aiContent + '</pre>';
                    }
                } else {
                    console.warn('Diff library not loaded. Displaying AI content only.');
                    diffOutput = '<pre>' + aiContent + '</pre>';
                }

                resultDiv.innerHTML = '<h3>AI Redraft Output:</h3>' + diffOutput;

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

    saveButton.addEventListener('click', function() {
        const redraft = form.dataset.redraft;
        const postId = form.dataset.postId;

        if (!redraft || !postId) {
            resultDiv.innerHTML += '<p style="color: red;">No content to save.</p>';
            return;
        }

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
