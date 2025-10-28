# Timetable Extractor

A modern React/TypeScript application for extracting and viewing timetables based on class, level, and academic year. Built with a Node.js/Express backend and MySQL database.

## Features

- 🔍 **Advanced Filtering**: Filter timetables by class, level, academic year, and day of week
- 📱 **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices
- 🎨 **Modern UI**: Clean, intuitive interface built with Tailwind CSS
- ⚡ **Fast Performance**: Optimized database queries and efficient React components
- 🔒 **Secure API**: Rate limiting, CORS protection, and input validation
- 📊 **Real-time Updates**: Dynamic filtering without page reloads

## Tech Stack

### Frontend
- **React 18** with TypeScript
- **Tailwind CSS** for styling
- **Axios** for API communication
- **Lucide React** for icons

### Backend
- **Node.js** with Express.js
- **TypeScript** for type safety
- **MySQL2** for database connectivity
- **Helmet** for security
- **CORS** for cross-origin requests
- **Rate Limiting** for API protection

### Database
- **MySQL 8.0+** with optimized schema
- **Indexed queries** for performance
- **Sample data** included for testing

## Project Structure

```
timetable-extractor/
├── backend/                 # Express.js API server
│   ├── src/
│   │   ├── config/         # Database configuration
│   │   ├── controllers/    # API route handlers
│   │   ├── models/         # Database models
│   │   ├── routes/         # API routes
│   │   ├── middleware/     # Custom middleware
│   │   └── server.ts       # Main server file
│   ├── database/           # Database schema and setup
│   └── package.json
├── frontend/               # React application
│   ├── src/
│   │   ├── components/     # React components
│   │   ├── services/       # API services
│   │   ├── types/          # TypeScript types
│   │   ├── utils/          # Helper functions
│   │   └── App.tsx         # Main app component
│   └── package.json
└── package.json            # Root package.json
```

## Quick Start

### Prerequisites
- Node.js 16+ and npm
- MySQL 8.0+
- Git

### 1. Clone and Install
```bash
git clone <repository-url>
cd timetable-extractor
npm run install-all
```

### 2. Database Setup
```bash
# Create database and tables
mysql -u root -p < backend/database/schema.sql

# Or manually:
# 1. Open MySQL client
# 2. Run the contents of backend/database/schema.sql
```

### 3. Environment Configuration
```bash
# Backend
cp backend/.env.example backend/.env
# Edit backend/.env with your database credentials

# Frontend
cp frontend/.env.example frontend/.env
# Edit frontend/.env if needed
```

### 4. Start Development Servers
```bash
# Start both backend and frontend
npm run dev

# Or start individually:
npm run server  # Backend on http://localhost:5000
npm run client  # Frontend on http://localhost:3000
```

### 5. Access the Application
- Frontend: http://localhost:3000
- Backend API: http://localhost:5000/api
- Health Check: http://localhost:5000/health

## API Endpoints

### Timetables
- `GET /api/timetables` - Get all timetables with optional filters
- `GET /api/timetables/filters` - Get available filter options
- `GET /api/timetables/:id` - Get specific timetable by ID

### Query Parameters
- `class_name` - Filter by class (e.g., "10A")
- `level` - Filter by level (e.g., "Grade 10")
- `academic_year` - Filter by year (e.g., "2024")
- `day_of_week` - Filter by day (e.g., "Monday")

### Example Requests
```bash
# Get all timetables for Grade 10A in 2024
GET /api/timetables?class_name=10A&level=Grade%2010&academic_year=2024

# Get Monday timetables
GET /api/timetables?day_of_week=Monday

# Get filter options
GET /api/timetables/filters
```

## Database Schema

### Timetables Table
```sql
CREATE TABLE timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    level VARCHAR(20) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    teacher VARCHAR(100) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Development

### Backend Development
```bash
cd backend
npm run dev          # Start with nodemon
npm run build        # Build TypeScript
npm test            # Run tests
```

### Frontend Development
```bash
cd frontend
npm start           # Start development server
npm run build       # Build for production
npm test           # Run tests
```

### Adding New Features
1. **Backend**: Add new routes in `backend/src/routes/`
2. **Frontend**: Create new components in `frontend/src/components/`
3. **Types**: Update TypeScript interfaces in `frontend/src/types/`
4. **API**: Add new service methods in `frontend/src/services/`

## Production Deployment

### Backend
```bash
cd backend
npm run build
npm start
```

### Frontend
```bash
cd frontend
npm run build
# Serve the build folder with nginx, Apache, or any static file server
```

### Environment Variables
- `DB_HOST` - Database host
- `DB_PORT` - Database port
- `DB_USER` - Database username
- `DB_PASSWORD` - Database password
- `DB_NAME` - Database name
- `PORT` - Server port
- `NODE_ENV` - Environment (development/production)
- `CORS_ORIGIN` - Allowed CORS origins

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

MIT License - see LICENSE file for details

## Support

For issues and questions:
1. Check the documentation
2. Search existing issues
3. Create a new issue with detailed information

## Sample Data

The application includes sample data for:
- Multiple classes (9A, 10A, 10B, 11A, 12A)
- Different academic levels (Grade 9-12)
- Multiple academic years (2023, 2024)
- Various subjects and teachers
- Complete weekly schedules