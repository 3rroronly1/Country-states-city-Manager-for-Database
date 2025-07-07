# Bulk Location Management System

A PHP-based web application for managing geographical locations (Countries, States/Provinces, and Cities) with both individual and bulk entry capabilities. This system is part of the FlightBook Admin interface.

## Features

### 1. Individual Entry
- Add individual countries with names and codes
- Add states/provinces with optional state codes
- Add cities with proper country and state associations
- Duplicate checking to prevent redundant entries
- Case-insensitive validation

### 2. Bulk Entry
- Bulk add states/provinces to a country
- Bulk add cities to a state
- Comma-separated input format
- Detailed success/failure reporting
- Duplicate detection within batch and existing records

### 3. List View
- View all locations in a paginated format
- Search functionality
- Hierarchical display (Country → State → City)

### 4. Cleanup Tools
- Remove duplicate entries while preserving data integrity
- Automated cleanup for countries, states, and cities

### 5. Test Dropdowns
- Test cascading dropdown functionality
- Real-time validation

## Technical Features

- AJAX-powered cascading dropdowns
- Prepared statements for SQL injection prevention
- Case-insensitive duplicate checking
- Responsive design using Tailwind CSS
- Error handling and user feedback
- RESTful API endpoints for location data

## Requirements

- PHP 7.4 or higher
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- Modern web browser

## Database Structure

The system requires three main tables:

```sql
CREATE TABLE countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(3) NOT NULL
);

CREATE TABLE states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    country_id INT NOT NULL,
    code VARCHAR(10),
    FOREIGN KEY (country_id) REFERENCES countries(id)
);

CREATE TABLE cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    state_id INT NOT NULL,
    FOREIGN KEY (state_id) REFERENCES states(id)
);
```

## Installation

1. Clone the repository:
```bash
git clone [repository-url]
```

2. Configure your database connection in `config.php`:
```php
$conn = new mysqli('localhost', 'username', 'password', 'database');
```

3. Import the database schema (if not already set up)

4. Ensure proper file permissions:
```bash
chmod 644 *.php
```

5. Configure your web server to serve the application

## Usage

### Individual Entry
1. Navigate to the "Individual Entry" tab
2. Choose the type of location to add (Country/State/City)
3. Fill in the required information
4. Submit the form

### Bulk Entry
1. Go to the "Bulk Entry" tab
2. Select the target country (for states) or state (for cities)
3. Enter comma-separated location names
4. Submit to process all entries at once

### List View
1. Access the "List View" tab
2. Use filters and search to find specific locations
3. Navigate through pages of results

### Cleanup
1. Visit the "Cleanup" tab
2. Select the type of cleanup needed
3. Confirm the action

## Security Considerations

- All SQL queries use prepared statements
- Input validation and sanitization
- Case-insensitive duplicate checking
- Error handling and logging
- AJAX request validation

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Uses [Tailwind CSS](https://tailwindcss.com/) for styling
