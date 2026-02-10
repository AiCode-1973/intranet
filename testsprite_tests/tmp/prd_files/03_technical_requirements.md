# Technical Requirements & Design

## Tech Stack
- **Backend**: PHP 7.4+ (Vanilla).
- **Frontend**: HTML5, JavaScript, Tailwind CSS for styling.
- **Database**: MySQL 5.7+ / MariaDB.
- **Icons**: Lucide Icons.

## Design Principles
- **Aesthetic**: Modern, premium look with a focus on usability and clarity.
- **Responsiveness**: Layout must adapt to different screen sizes (Mobile/Desktop).
- **Interactivity**: Use of micro-animations and smooth transitions (Tailwind/Lucide).

## Security
- Input sanitization (custom `sanitize` function).
- Prepared statements for SQL queries to prevent injection.
- Session-based authentication (`requireLogin` and `isAdmin` checks).
- File upload restrictions for document security.

## Database Schema Highlights
- `usuarios`: Central user table with sector references and admin flags.
- `agenda`: Event storage with resource reservation flags.
- `chamados`: Support ticket tracking across IT and Maintenance.
- `rh_mensagens`: Individual communication storage.
- `edu_cursos/aulas/provas`: Schema for the education module.
