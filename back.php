<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Back to Top Button with Spinner</title>
  <style>
    /* Basic page styling for demonstration */
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    .content {
      height: 2000px;
      background: linear-gradient(180deg, #f0f0f0, #c0c0c0);
      padding: 20px;
    }

    /* Back to Top button styling */
    #back-to-top {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background-color: #3498db;
      color: white;
      border: none;
      outline: none;
      cursor: pointer;
      display: none; /* Hidden until scrolled down */
      align-items: center;
      justify-content: center;
      font-size: 24px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
      z-index: 1000;
      transition: background-color 0.3s ease;
    }

    #back-to-top:hover {
      background-color: #2980b9;
    }

    /* Spinner styling */
    .spinner {
      border: 4px solid rgba(255, 255, 255, 0.3);
      border-top: 4px solid white;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      animation: spin 1s linear infinite;
      display: none; /* Hidden by default */
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>

  <div class="content">
    <h1>Scroll Down</h1>
    <p>Keep scrolling to see the "Back to Top" button appear.</p>
  </div>

  <!-- Back to Top button -->
  <button id="back-to-top">
    <!-- Arrow icon -->
    <span id="arrow">&#8679;</span>
    <!-- Spinner -->
    <div class="spinner" id="spinner"></div>
  </button>

  <script>
    const backToTopBtn = document.getElementById('back-to-top');
    const spinner = document.getElementById('spinner');
    const arrow = document.getElementById('arrow');

    // Show the button when scrolling down
    window.addEventListener('scroll', () => {
      if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
        backToTopBtn.style.display = 'flex';
      } else {
        backToTopBtn.style.display = 'none';
      }
    });

    // Click event to scroll back to top
    backToTopBtn.addEventListener('click', () => {
      // Hide arrow and display spinner
      arrow.style.display = 'none';
      spinner.style.display = 'block';

      // Smooth scroll to the top
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });

      // Use a timeout to simulate when scrolling is "done"
      // (There is no native scroll-end event for smooth scrolling)
      setTimeout(() => {
        spinner.style.display = 'none';
        arrow.style.display = 'block';
      }, 1000); // Adjust the timeout based on scroll duration if needed
    });
  </script>

</body>
</html>
