<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI CUSTOMER SUPPORT</title>
    @vite('resources/css/app.css')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container">
    <h1>AI CUSTOMER SUPPORT</h1>

    <!-- Document Upload and Query Section -->
    <h2>Ask a Question From Upload Documents</h2>
    <form id="upload-form" enctype="multipart/form-data">
        @csrf
        <input type="file" name="document" accept=".pdf,.txt">
        <button type="submit">Upload Document</button>
        <p class="loading" id="upload-loading" style="display: none;">Uploading...</p>
        <p class="error" id="upload-error" style="display: none;"></p>
    </form>
    <form id="query-form">
        @csrf
        <textarea name="question" id="question" placeholder="Ask a question about your documents..." required></textarea>
        <button type="submit">Ask Question</button>
        <p class="loading" id="query-loading" style="display: none;">Processing...</p>
        <p class="error" id="query-error" style="display: none;"></p>
    </form>
    <h3>Responses</h3>
    <div id="gemini-response" class="model-response">
        <h4>Gemini</h4>
        <p>No response yet.</p>
    </div>


    <!-- Summarization Section -->
    <h2>Generate Summary from Document</h2>
    <form id="summary-form" enctype="multipart/form-data">
        @csrf
        <label>Select Document:</label>
        <select name="selected_document" required>
            <option value="">Select a document</option>
        </select>
        <select name="summary_type">
            <option value="short">Short Summary (100-150 words)</option>
            <option value="detailed">Detailed Summary (150-200 words)</option>
            <option value="key_points">Key Points (3-5 bullet points)</option>
        </select>
        <button type="submit">Generate Summary</button>
        <p class="loading" id="summary-loading" style="display: none;">Generating...</p>
        <p class="error" id="summary-error" style="display: none;"></p>
    </form>
    <div id="summary-result" class="model-response" style="background: #e9f7ef;"></div>
</div>

<script>
    $(document).ready(function () {
        loadDocuments();

        $('#upload-form').on('submit', function (e) {
            e.preventDefault();
            $('#upload-loading').show();
            $('#upload-error').hide();

            const formData = new FormData(this);

            $.ajax({
                url: '{{ route("document.upload") }}',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (res) {
                    if (res.status === 'success') {
                        loadDocuments();
                        $('#upload-form')[0].reset();
                    } else {
                        $('#upload-error').text('Error: ' + (res.message || 'Invalid request')).show();
                    }
                },
                error: function (xhr) {
                    $('#upload-error').text('Server error: ' + (xhr.responseJSON?.message || 'Please try again')).show();
                },
                complete: function () {
                    $('#upload-loading').hide();
                }
            });
        });

        $('#query-form').on('submit', function (e) {
            e.preventDefault();
            $('#query-loading').show();
            $('#query-error').hide();

            const formData = new FormData(this);

            $.ajax({
                url: '{{ route("document.query") }}',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (res) {
                    if (res.status === 'success') {
                        $('#gemini-response p').html(`${res.responses.gemini.response}<br><em>Time: ${res.responses.gemini.response_time}</em>`);
                        $('#tinyllama-response p').html(`${res.responses.tinyllama.response}<br><em>Time: ${res.responses.tinyllama.response_time}</em>`);
                        $('#query-form')[0].reset();
                    } else {
                        $('#query-error').text('Error: ' + (res.message || 'Invalid request')).show();
                    }
                },
                error: function (xhr) {
                    $('#query-error').text('Server error: ' + (xhr.responseJSON?.message || 'Please try again')).show();
                },
                complete: function () {
                    $('#query-loading').hide();
                }
            });
        });

        $('#summary-form').on('submit', function (e) {
            e.preventDefault();
            $('#summary-loading').show();
            $('#summary-error').hide();

            const formData = new FormData(this);

            $.ajax({
                url: '{{ route("document.summarize") }}',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (res) {
                    if (res.status === 'success') {
                        $('#summary-result').html('<strong>Summary:</strong><br>' + res.summary);
                    } else {
                        $('#summary-error').text('Error: ' + (res.message || 'Invalid request')).show();
                    }
                },
                error: function (xhr) {
                    $('#summary-error').text('Server error: ' + (xhr.responseJSON?.message || 'Please try again')).show();
                },
                complete: function () {
                    $('#summary-loading').hide();
                }
            });
        });

        function loadDocuments() {
            $.get('{{ route("document.list") }}', function (res) {
                if (res.status === 'success') {
                    const $select = $('select[name="selected_document"]');
                    $select.empty();
                    $select.append('<option value="">Select a document</option>');
                    $.each(res.documents, function (id, name) {
                        $select.append(`<option value="${id}">${name}</option>`);
                    });
                }
            });
        }
    });
</script>
</body>
</html>