<!DOCTYPE html>
<html lang="en">
<head>
    <title>Session Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa; /* Light background for contrast */
        }
        .navbar {
            background-color: #800020 !important; /* Consistent navbar color */
        }
        .container {
            margin-top: 20px;
            width: 70%; /* Cover only 70% of the page */
        }
        .card {
            border: none; /* Remove border */
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* Add shadow for depth */
        }
        .form-label {
            font-weight: bold;
        }
        .generate-btn {
            background-color: #800020; /* Consistent button color */
            color: #FFD700; /* Button text color */
            border: none; /* Remove border */
            transition: background-color 0.3s; /* Smooth transition */
            padding: 10px 30px; /* Padding for button */
            border-radius: 5px; /* Rounded corners */
            font-weight: bold; /* Bold text */
            display: block;
            margin: 20px auto; /* Center the button */
        }
        .generate-btn:hover {
            background-color: #600010; /* Darker shade on hover */
        }
        .session-header {
            background-color: #800020; /* Background color for the session header */
            color: #FFD700; /* Text color */
            padding: 10px; /* Padding for the header */
            border-radius: 5px; /* Rounded corners */
            text-align: center; /* Center the text */
            margin-bottom: 20px; /* Space below the header */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content-area">
            <div class="session-header">
                <h2>SESSION</h2>
            </div>

            <div class="form-container">
                <div class="form-group">
                    <label class="form-label">Mode:</label>
                    <select class="form-select">
                        <option selected>Select Mode</option>
                        <option value="Regular">Regular</option>
                        <option value="Weekend">Weekend</option>
                        <option value="Evening">Evening</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Semester:</label>
                    <select class="form-select">
                        <option selected>Select Semester</option>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    </select>
                </div>

                <button class="generate-btn">GENERATE</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>