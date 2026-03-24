<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * API client for Mastermind Assistant dashboard
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant;

defined('MOODLE_INTERNAL') || die();

/**
 * Centralized HTTP client for all Mastermind Dashboard API calls.
 *
 * All external classes use this instead of making direct OpenAI cURL requests.
 * The dashboard handles all AI logic (prompts, function schemas, OpenAI calls).
 */
class api_client {

    /** @var string Dashboard base URL */
    private string $baseUrl;

    /** @var string API key (ma_live_xxx) */
    private string $apiKey;

    /**
     * Constructor - reads config from plugin settings.
     *
     * @throws \moodle_exception if settings are not configured
     */
    public function __construct() {
        $this->baseUrl = rtrim(get_config('block_mastermind_assistant', 'dashboard_url') ?: '', '/');
        $this->apiKey = get_config('block_mastermind_assistant', 'api_key') ?: '';

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new \moodle_exception('settings_not_configured', 'block_mastermind_assistant');
        }
    }

    /**
     * Make a request to the dashboard API.
     *
     * @param string $endpoint e.g. '/api/ma/analyze-course'
     * @param array $data POST body (will be JSON-encoded)
     * @param string $method 'GET' or 'POST'
     * @param int $timeout seconds (default 300 for AI calls)
     * @return array decoded JSON response
     * @throws \Exception on failure
     */
    public function request(string $endpoint, array $data = [], string $method = 'POST', int $timeout = 300): array {
        $url = $this->baseUrl . $endpoint;

        global $CFG;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'X-LMS-Origin: ' . ($CFG->wwwroot ?? ''),
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Dashboard API request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $msg = $decoded['error'] ?? "HTTP {$httpCode}";
            throw new \Exception('Dashboard API error: ' . $msg);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \Exception('Invalid JSON response from dashboard');
        }

        return $decoded;
    }

    /**
     * Analyze course structure and get AI recommendations.
     *
     * @param array $courseData {course, sections, activities}
     * @return array
     */
    public function analyzeCourse(array $courseData): array {
        return $this->request('/api/ma/analyze-course', $courseData);
    }

    /**
     * Full analysis: analyze course AND generate updated structure in a single call.
     *
     * Requires the /api/ma/full-analysis endpoint on the dashboard.
     * If the endpoint doesn't exist yet, callers should fall back to
     * analyzeCourse() + generateStructure() separately.
     *
     * @param array $courseData {course, sections, activities}
     * @return array {recommendations: string, structure: {sections: [...]}}
     */
    public function fullAnalysis(array $courseData): array {
        return $this->request('/api/ma/full-analysis', $courseData);
    }

    /**
     * Generate a complete course structure from scratch.
     *
     * @param string $courseName
     * @return array
     */
    public function generateCourse(string $courseName): array {
        return $this->request('/api/ma/generate-course', ['coursename' => $courseName]);
    }

    /**
     * Generate a course structure from an uploaded document (PDF, DOCX, TXT).
     *
     * @param string $fileData Base64-encoded file content
     * @param string $fileType MIME type (application/pdf, etc.)
     * @param string $fileName Original file name
     * @return array
     */
    public function generateCourseFromDocument(string $fileData, string $fileType, string $fileName): array {
        return $this->request('/api/ma/generate-course-from-document', [
            'file_data' => $fileData,
            'file_type' => $fileType,
            'file_name' => $fileName,
        ], 'POST', 600);
    }

    /**
     * Generate updated course structure based on recommendations.
     *
     * @param array $courseData
     * @param string $recommendations
     * @return array
     */
    public function generateStructure(array $courseData, string $recommendations): array {
        return $this->request('/api/ma/generate-structure', [
            'coursedata' => $courseData,
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Generate quiz questions.
     *
     * @param string $quizName
     * @param string $quizDescription
     * @param array $existingQuestions
     * @return array
     */
    public function generateQuiz(
        string $quizName,
        string $quizDescription,
        array $existingQuestions = [],
        string $difficultyLevel = '',
        int $questionCount = 8,
        string $academicLevel = '',
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-quiz', [
            'quizname' => $quizName,
            'quizdescription' => $quizDescription,
            'existing_questions' => $existingQuestions,
            'difficulty_level' => $difficultyLevel,
            'question_count' => $questionCount,
            'academic_level' => $academicLevel,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Generate page content.
     *
     * @param string $courseName
     * @param string $pageName
     * @param string $pageDescription
     * @return array
     */
    public function generatePageContent(
        string $courseName,
        string $pageName,
        string $pageDescription,
        string $contentType = '',
        string $academicLevel = '',
        string $targetLength = '',
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-page', [
            'coursename' => $courseName,
            'pagename' => $pageName,
            'pagedescription' => $pageDescription,
            'content_type' => $contentType,
            'academic_level' => $academicLevel,
            'target_length' => $targetLength,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Generate assignment instructions.
     *
     * @param string $courseName
     * @param string $assignmentName
     * @param string $assignmentDescription
     * @return array
     */
    public function generateAssignment(
        string $courseName,
        string $assignmentName,
        string $assignmentDescription,
        string $assignmentType = '',
        string $academicLevel = '',
        string $scopeLength = '',
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-assignment', [
            'coursename' => $courseName,
            'assignmentname' => $assignmentName,
            'assignmentdescription' => $assignmentDescription,
            'assignment_type' => $assignmentType,
            'academic_level' => $academicLevel,
            'scope_length' => $scopeLength,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Generate forum content (introduction + discussion topics).
     */
    public function generateForum(
        string $courseName,
        string $forumName,
        string $forumDescription,
        string $forumType = '',
        string $academicLevel = '',
        int $discussionCount = 5,
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-forum', [
            'coursename' => $courseName,
            'forumname' => $forumName,
            'forumdescription' => $forumDescription,
            'forum_type' => $forumType,
            'academic_level' => $academicLevel,
            'discussion_count' => $discussionCount,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Generate lesson content (branching pages).
     */
    public function generateLesson(
        string $courseName,
        string $lessonName,
        string $lessonDescription,
        string $academicLevel = '',
        int $pageCount = 6,
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-lesson', [
            'coursename' => $courseName,
            'lessonname' => $lessonName,
            'lessondescription' => $lessonDescription,
            'academic_level' => $academicLevel,
            'page_count' => $pageCount,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Generate glossary entries.
     */
    public function generateGlossary(
        string $courseName,
        string $glossaryName,
        string $glossaryDescription,
        string $academicLevel = '',
        int $entryCount = 10,
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-glossary', [
            'coursename' => $courseName,
            'glossaryname' => $glossaryName,
            'glossarydescription' => $glossaryDescription,
            'academic_level' => $academicLevel,
            'entry_count' => $entryCount,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Generate book content (multi-chapter).
     */
    public function generateBook(
        string $courseName,
        string $bookName,
        string $bookDescription,
        string $academicLevel = '',
        int $chapterCount = 5,
        string $targetLength = '',
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-book', [
            'coursename' => $courseName,
            'bookname' => $bookName,
            'bookdescription' => $bookDescription,
            'academic_level' => $academicLevel,
            'chapter_count' => $chapterCount,
            'target_length' => $targetLength,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Generate URL resource recommendations.
     */
    public function generateUrl(
        string $courseName,
        string $urlName,
        string $urlDescription,
        string $academicLevel = '',
        int $resourceCount = 5,
        string $sectionName = '',
        array $courseActivities = []
    ): array {
        return $this->request('/api/ma/generate-url', [
            'coursename' => $courseName,
            'urlname' => $urlName,
            'urldescription' => $urlDescription,
            'academic_level' => $academicLevel,
            'resource_count' => $resourceCount,
            'section_name' => $sectionName,
            'course_activities' => $courseActivities,
        ]);
    }

    /**
     * Log user feedback on AI-generated content.
     */
    public function logGenerationFeedback(
        string $action,
        string $moduleType,
        string $activityName = '',
        string $courseName = ''
    ): array {
        return $this->request('/api/ma/generation-feedback', [
            'action' => $action,
            'module_type' => $moduleType,
            'activity_name' => $activityName,
            'course_name' => $courseName,
        ], 'POST', 15);
    }

    /**
     * Test connection to the dashboard.
     *
     * @return array
     */
    public function testConnection(): array {
        return $this->request('/api/ma/test-connection', [], 'GET', 15);
    }

    /**
     * Get account info (tier, usage).
     *
     * @return array
     */
    public function getAccount(): array {
        return $this->request('/api/ma/account', [], 'GET', 15);
    }
}
