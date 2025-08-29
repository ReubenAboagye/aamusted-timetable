  <!-- Footer -->
  <div class="footer" id="footer">&copy; 2025 TimeTable Generator</div>

  <!-- Back to Top Button -->
  <button id="backToTop">
    <svg width="50" height="50" viewBox="0 0 50 50">
      <circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"/>
    </svg>
    <i class="fas fa-arrow-up arrow-icon"></i>
  </button>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
  <script>
    // Wait for DOM to be fully loaded before running any JavaScript
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, initializing all functionality...');
      
      // Time update functionality
      function updateTime(){ 
        const now=new Date(); 
        const timeString=now.toLocaleTimeString('en-US',{hour12:true,hour:'2-digit',minute:'2-digit',second:'2-digit'}); 
        const el=document.getElementById('currentTime'); 
        if(el) el.textContent=timeString; 
      }
      setInterval(updateTime,1000); 
      updateTime();
      
      // Stream update functionality
      function updateCurrentStream() {
        const now = new Date();
        const currentHour = now.getHours();
        const streamEl = document.getElementById('streamName');
        
        if (streamEl) {
          let streamName = 'Morning';
          if (currentHour >= 6 && currentHour < 12) {
            streamName = 'Morning';
          } else if (currentHour >= 12 && currentHour < 17) {
            streamName = 'Afternoon';
          } else if (currentHour >= 17 && currentHour < 22) {
            streamName = 'Evening';
          } else {
            streamName = 'Night';
          }
          streamEl.textContent = streamName;
        }
      }
      setInterval(updateCurrentStream, 60000); // Update every minute
      updateCurrentStream();
      
      // Sidebar toggle with persistent user preference (localStorage)
      const storageKey = 'sidebarCollapsed';
      const sidebarToggle = document.getElementById('sidebarToggle');
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      const footerEl = document.getElementById('footer');

      console.log('Sidebar elements found:', {
        sidebarToggle: !!sidebarToggle,
        sidebar: !!sidebar,
        mainContent: !!mainContent,
        footerEl: !!footerEl
      });

      // Test if elements are actually found
      if (sidebarToggle) console.log('Sidebar toggle button found:', sidebarToggle);
      if (sidebar) console.log('Sidebar found:', sidebar);
      if (mainContent) console.log('Main content found:', mainContent);
      if (footerEl) console.log('Footer found:', footerEl);

      function applyState(collapsed){
        if (!sidebar || !mainContent || !footerEl) {
          console.log('Missing required elements for sidebar toggle');
          return;
        }
        console.log('Applying sidebar state:', collapsed);
        if (collapsed) {
          sidebar.classList.add('collapsed');
          mainContent.classList.add('collapsed');
          footerEl.classList.add('collapsed');
        } else {
          sidebar.classList.remove('collapsed');
          mainContent.classList.remove('collapsed');
          footerEl.classList.remove('collapsed');
        }
      }

      // Initialize from stored preference (defaults to visible)
      try {
        const stored = localStorage.getItem(storageKey);
        const collapsed = stored === 'true';
        console.log('Stored sidebar state:', stored, 'Collapsed:', collapsed);
        applyState(collapsed);
      } catch (e) {
        console.log('Storage error:', e);
      }

      if (sidebarToggle) {
        console.log('Adding click event listener to sidebar toggle');
        sidebarToggle.addEventListener('click', function(e){
          e.preventDefault();
          console.log('Sidebar toggle clicked');
          if (!sidebar || !mainContent || !footerEl) {
            console.log('Missing required elements for sidebar toggle');
            return;
          }
          const collapsed = sidebar.classList.toggle('collapsed');
          console.log('Toggling sidebar to collapsed:', collapsed);
          if (collapsed) {
            mainContent.classList.add('collapsed');
            footerEl.classList.add('collapsed');
          } else {
            mainContent.classList.remove('collapsed');
            footerEl.classList.remove('collapsed');
          }
          try { localStorage.setItem(storageKey, String(collapsed)); } catch (e) { }
        });
      } else {
        console.log('Sidebar toggle button not found');
      }
      
      // Back to top & progress functionality
      const backToTopButton = document.getElementById('backToTop'); 
      const progressCircle = document.getElementById('progressCircle'); 
      if(progressCircle){ 
        const circumference = 2*Math.PI*20; 
        progressCircle.style.strokeDasharray = circumference; 
        progressCircle.style.strokeDashoffset = circumference; 
        window.addEventListener('scroll',function(){ 
          const scrollTop = document.documentElement.scrollTop||document.body.scrollTop; 
          if(backToTopButton) backToTopButton.style.display = scrollTop>100?'block':'none'; 
          const scrollHeight = document.documentElement.scrollHeight-document.documentElement.clientHeight; 
          const scrollPercentage = scrollTop/(scrollHeight||1); 
          const offset = circumference-(scrollPercentage*circumference); 
          progressCircle.style.strokeDashoffset = offset; 
        }); 
        if(backToTopButton) backToTopButton.addEventListener('click',function(){ 
          window.scrollTo({ top:0, behavior:'smooth' }); 
        }); 
      }
      
      // Global table search functionality
      const searchInputs = document.querySelectorAll('.search-input');
      searchInputs.forEach(input => {
          input.addEventListener('keyup', function() {
              const searchValue = this.value.toLowerCase();
              const tableId = this.closest('.table-container').querySelector('table').id;
              const table = document.getElementById(tableId);
              if (!table) return;
              
              const rows = table.querySelectorAll('tbody tr');
              rows.forEach(row => {
                  const cells = row.querySelectorAll('td');
                  let matchFound = false;
                  for (let i = 0; i < cells.length - 1; i++) { // Exclude last column (Actions)
                      if (cells[i].textContent.toLowerCase().includes(searchValue)) {
                          matchFound = true;
                          break;
                      }
                  }
                  row.style.display = matchFound ? '' : 'none';
              });
          });
      });
      
      // Initialize Bootstrap collapse functionality for sidebar dropdowns
      const dropdownHeaders = document.querySelectorAll('.dropdown-header');
      dropdownHeaders.forEach(header => {
          header.addEventListener('click', function(e) {
              e.preventDefault();
              const targetId = this.getAttribute('data-bs-target');
              const target = document.querySelector(targetId);
              
              if (target) {
                  // Check if this dropdown was marked as "keep open"
                  const shouldKeepOpen = target.getAttribute('data-keep-open') === 'true';
                  
                  if (shouldKeepOpen) {
                      // If it was marked as keep open, just close it and remove the mark
                      target.classList.remove('show');
                      this.setAttribute('aria-expanded', 'false');
                      target.removeAttribute('data-keep-open');
                  } else {
                      // Normal toggle behavior
                      if (target.classList.contains('show')) {
                          target.classList.remove('show');
                          this.setAttribute('aria-expanded', 'false');
                      } else {
                          // Close other open dropdowns first
                          document.querySelectorAll('.collapse.show').forEach(openCollapse => {
                              if (openCollapse !== target) {
                                  openCollapse.classList.remove('show');
                                  openCollapse.removeAttribute('data-keep-open');
                                  const openHeader = document.querySelector(`[data-bs-target="#${openCollapse.id}"]`);
                                  if (openHeader) {
                                      openHeader.setAttribute('aria-expanded', 'false');
                                  }
                              }
                          });
                          
                          target.classList.add('show');
                          this.setAttribute('aria-expanded', 'true');
                      }
                  }
              }
          });
      });
      
      // Prevent dropdowns from closing when clicking on navigation links
      const sidebarNavLinks = document.querySelectorAll('.sidebar .nav-link');
      sidebarNavLinks.forEach(link => {
          link.addEventListener('click', function(e) {
              // Find the parent dropdown if this link is inside one
              const parentDropdown = this.closest('.collapse');
              if (parentDropdown) {
                  // Mark this dropdown as "keep open" by adding a data attribute
                  parentDropdown.setAttribute('data-keep-open', 'true');
                  
                  // Ensure the dropdown stays visible
                  parentDropdown.classList.add('show');
                  
                  // Update the corresponding header's aria-expanded attribute
                  const dropdownId = parentDropdown.id;
                  const header = document.querySelector(`[data-bs-target="#${dropdownId}"]`);
                  if (header) {
                      header.setAttribute('aria-expanded', 'true');
                  }
              }
          });
      });
      
      console.log('All functionality initialized successfully');
    });
  </script>


