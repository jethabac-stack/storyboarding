# Kahoot Clone

A game-based learning platform built with HTML, Tailwind CSS, JavaScript, PHP, and MySQL.

## Features

- **User Authentication** - Register and login as Instructor or Student
- **Quiz Creation** - Create quizzes with multiple-choice questions, images, and time limits
- **Live Games** - Host real-time quiz games with unique game PINs
- **Player Joining** - Players join games using a 6-digit PIN
- **Real-time Leaderboard** - Track scores during the game
- **Question Timer** - Configurable time limits per question
- **Points System** - Earn points based on correct answers and speed

## Tech Stack

- **Frontend**: HTML, Tailwind CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL

## Project Structure

```
kahoot-clone/
├── api/
│   ├── config.php      # Database configuration
│   ├── db.php          # Database connection
│   ├── auth.php        # Authentication endpoints
│   ├── quiz.php        # Quiz management endpoints
│   └── game.php        # Game logic endpoints
├── assets/
│   ├── css/            # CSS files (if needed)
│   └── js/             # JavaScript files
├── index.html          # Landing page
├── login.html          # Login page
├── register.html       # Registration page
├── dashboard.html      # User dashboard
├── quiz-editor.html    # Quiz creation/editing
├── host-game.html      # Host game interface
├── join.html           # Player join interface
└── database.sql        # Database schema
```

## Setup Instructions

### 1. Database Setup

1. Open MySQL (via XAMPP, WAMP, or command line)
2. Create a new database:
   ```sql
   CREATE DATABASE kahoot_clone;
   ```
3. Import the database schema:
   ```sql
   USE kahoot_clone;
   SOURCE path/to/database.sql;
   ```

### 2. Configure Database Connection

Edit `api/config.php` and update the database credentials if needed:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kahoot_clone');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Start the Server

Using XAMPP:
1. Start Apache and MySQL in XAMPP Control Panel
2. Place the project folder in `htdocs` directory
3. Access via `http://localhost/kahoot-clone/`

Using PHP built-in server:
```bash
cd kahoot-clone
php -S localhost:8000
```

## How to Use

### For Instructors:
1. Register an account (select "Teacher/Instructor" role)
2. Login to the dashboard
3. Click "Create Quiz" to create a new quiz
4. Add questions with answers (mark the correct answer)
5. Publish the quiz
6. Click the play button to host a game
7. Share the Game PIN with players

### For Players:
1. Go to the Join Game page
2. Enter the Game PIN provided by the host
3. Enter your nickname
4. Answer questions when they appear
5. See your score on the final results screen

## API Endpoints

### Authentication
- `POST api/auth.php?action=register` - Register new user
- `POST api/auth.php?action=login` - Login user
- `GET api/auth.php?action=profile&user_id={id}` - Get user profile

### Quizzes
- `POST api/quiz.php?action=create_quiz` - Create quiz
- `GET api/quiz.php?action=get_quizzes&user_id={id}` - Get user's quizzes
- `GET api/quiz.php?action=get_quiz&quiz_id={id}` - Get quiz with questions
- `PUT api/quiz.php?action=update_quiz` - Update quiz
- `DELETE api/quiz.php?action=delete_quiz&quiz_id={id}` - Delete quiz
- `POST api/quiz.php?action=add_question` - Add question
- `PUT api/quiz.php?action=update_question` - Update question
- `DELETE api/quiz.php?action=delete_question&question_id={id}` - Delete question

### Games
- `POST api/game.php?action=create_game` - Create new game
- `POST api/game.php?action=join_game` - Join game
- `GET api/game.php?action=game_status&game_id={id}` - Get game status
- `POST api/game.php?action=start_game` - Start game
- `GET api/game.php?action=current_question&game_id={id}` - Get current question
- `POST api/game.php?action=submit_answer` - Submit answer
- `POST api/game.php?action=next_question` - Move to next question
- `GET api/game.php?action=leaderboard&game_id={id}` - Get leaderboard
- `GET api/game.php?action=game_results&game_id={id}` - Get final results

## License

MIT License