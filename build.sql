CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    picture TEXT,
    registered DATETIME NOT NULL,
    method VARCHAR(50) NOT NULL
);

CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('academic', 'career', 'course') NOT NULL,
    preference VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE
);

CREATE TABLE Courses (
    CourseID INT PRIMARY KEY AUTO_INCREMENT,
    course_name VARCHAR(100),
    credits INT,
    department VARCHAR(100),
    course_code VARCHAR(50),
    description TEXT,
    semester_offered VARCHAR(50),
    instructor VARCHAR(100),
    prerequisites TEXT
);

CREATE TABLE Interests (
    InterestID INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(100),
    area_of_interest TEXT,
    created_date DATE,
    updated_date DATE
);

CREATE TABLE Requirements (
    RequirementID INT PRIMARY KEY AUTO_INCREMENT,
    credits_earned INT,
    credits_remaining INT,
    total_credits INT,
    min_grade VARCHAR(2)
);

CREATE TABLE Minors (
    MinorID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    total_credits INT NOT NULL,
    description TEXT NOT NULL
);

CREATE TABLE MinorCourses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    MinorID INT NOT NULL,
    CourseID INT NOT NULL,
    FOREIGN KEY (MinorID) REFERENCES Minors(MinorID) ON DELETE CASCADE,
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID) ON DELETE CASCADE
);

CREATE TABLE MinorTags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    MinorID INT NOT NULL,
    tag VARCHAR(100) NOT NULL,
    FOREIGN KEY (MinorID) REFERENCES Minors(MinorID) ON DELETE CASCADE
);

-- INSERT SAMPLE DATA

INSERT INTO accounts (email, name, registered, method) VALUES
('johndoe@example.com', 'John Doe', NOW(), 'google'),
('janesmith@example.com', 'Jane Smith', NOW(), 'google'),
('alice@example.com', 'Alice Smith', NOW(), 'google'),
('green@example.com', 'Dr. Green', NOW(), 'google'),
('carol@example.com', 'Carol White', NOW(), 'google');

INSERT INTO Courses (course_name, credits, department, course_code, description, semester_offered, instructor, prerequisites) VALUES
('Web Development', 3, 'Informatics', 'INFO-W210', 'Introduction to web development', 'Fall', 'Dr. Brown', 'None'),
('Data Structures', 3, 'Computer Science', 'CSCI-C343', 'Study of data structures', 'Spring', 'Dr. White', 'CSCI-C212'),
('Probability and Statistics', 3, 'Mathematics', 'MATH210', 'Core concepts in probability', 'Fall', 'Prof. Taylor', NULL);

INSERT INTO Interests (subject, area_of_interest, created_date, updated_date) VALUES
('Web Development', 'Building websites and applications', '2024-01-01', '2024-01-15'),
('Applied Mathematics', 'Mathematics', '2022-09-01', '2024-05-01'),
('Algorithms', 'Optimizing problem-solving', '2023-06-10', '2023-12-20');

INSERT INTO Requirements (credits_earned, credits_remaining, total_credits, min_grade) VALUES
(6, 12, 18, 'B'),
(2, 9, 18, 'B'),
(9, 6, 15, 'C');

INSERT INTO Minors (name, department, total_credits, description) VALUES
('Data Science', 'Computer Science', 18, 'Focuses on data analysis, machine learning, and AI.'),
('Applied Mathematics', 'Mathematics', 15, 'Emphasizes real-world problem-solving using math.'),
('Genomics', 'Biology', 20, 'Studies the role of genes in biological functions.');

INSERT INTO MinorCourses (MinorID, CourseID) VALUES
(1, 1), -- Data Science requires 'Web Development'
(1, 2), -- Data Science requires 'Data Structures'
(2, 3), -- Applied Mathematics requires 'Probability and Statistics'
(3, 2); -- Genomics requires 'Data Structures'

INSERT INTO MinorTags (MinorID, tag) VALUES
(1, 'AI'),
(1, 'Data Science'),
(2, 'Mathematics'),
(3, 'Genomics'),
(3, 'Biology');

-- Relationship Tables

CREATE TABLE Take (
    user_id INT NOT NULL,
    CourseID INT NOT NULL,
    PRIMARY KEY (user_id, CourseID),
    FOREIGN KEY (user_id) REFERENCES accounts(id),
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID)
);

CREATE TABLE Selects (
    user_id INT NOT NULL,
    InterestID INT NOT NULL,
    PRIMARY KEY (user_id, InterestID),
    FOREIGN KEY (user_id) REFERENCES accounts(id),
    FOREIGN KEY (InterestID) REFERENCES Interests(InterestID)
);

CREATE TABLE Relate (
    CourseID INT NOT NULL,
    InterestID INT NOT NULL,
    PRIMARY KEY (CourseID, InterestID),
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID),
    FOREIGN KEY (InterestID) REFERENCES Interests(InterestID)
);

CREATE TABLE Involve (
    CourseID INT NOT NULL,
    RequirementID INT NOT NULL,
    PRIMARY KEY (CourseID, RequirementID),
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID),
    FOREIGN KEY (RequirementID) REFERENCES Requirements(RequirementID)
);

CREATE TABLE Recommends (
    InterestID INT NOT NULL,
    MinorID INT NOT NULL,
    PRIMARY KEY (InterestID, MinorID),
    FOREIGN KEY (InterestID) REFERENCES Interests(InterestID),
    FOREIGN KEY (MinorID) REFERENCES Minors(MinorID)
);

CREATE TABLE Fulfill (
    RequirementID INT NOT NULL,
    MinorID INT NOT NULL,
    PRIMARY KEY (RequirementID, MinorID),
    FOREIGN KEY (RequirementID) REFERENCES Requirements(RequirementID),
    FOREIGN KEY (MinorID) REFERENCES Minors(MinorID)
);

-- Insert Sample Data for Relationships
INSERT INTO Take (user_id, CourseID) VALUES
(1, 1), 
(1, 2), 
(2, 3), 
(4, 2); 

INSERT INTO Selects (user_id, InterestID) VALUES
(1, 1), 
(2, 2), 
(4, 3); 

INSERT INTO Involve (CourseID, RequirementID) VALUES
(1, 1), 
(2, 1), 
(3, 2), 
(4, 3);

INSERT INTO Fulfill (MinorID, RequirementID) VALUES
(1, 1), 
(2, 2),
(3, 3);

INSERT INTO Recommends (InterestID, MinorID) VALUES
(1, 1),
(2, 2),
(3, 3);