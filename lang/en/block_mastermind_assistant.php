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
 * English language strings for Mastermind Assistant
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Language strings for Mastermind Assistant block.

$string['pluginname'] = 'Mastermind Assistant';
$string['insights_title'] = 'Insights';
$string['coursecompletionrate'] = 'Course Completion Rate: {$a}%';
$string['averagefinalgrade'] = 'Average Final Grade: {$a}%';
$string['dropoffsection'] = 'Drop-off Section: {$a}';
$string['forumactivity'] = 'Forum Activity: {$a} posts/learner';
$string['averagetimeincourse'] = 'Average Time in Course: {$a}';
$string['learnersatisfaction'] = 'Learner Satisfaction: {$a}/5';
$string['unknown'] = 'Unknown';
$string['no_activity'] = 'No activity';
$string['last_activity'] = 'Last Activity';
$string['resources'] = 'Resources';
$string['no_feedback'] = 'No feedback activity';
$string['notapplicable'] = 'Course insights are not applicable on this page.';
$string['nav_manage_courses'] = 'Manage Courses & Categories';
$string['nav_search_courses'] = 'Search Courses';
$string['nav_browse_categories'] = 'Browse Categories';
$string['getrecommendations'] = 'Get Recommendations';
$string['errorgetcoursedata'] = 'Error retrieving course data';
$string['openai_connected'] = 'OpenAI connection successful';
$string['openai_failed'] = 'OpenAI connection failed';
$string['getting_ai_recommendations'] = 'Getting AI Recommendations...';

// Icon strings.
$string['openai_success_icon'] = '✓';
$string['openai_warning_icon'] = '⚠';
$string['openai_error_icon'] = '✗';

// Course search strings.
$string['search_course_placeholder'] = 'Type a course name...';
$string['searching'] = 'Searching courses...';
$string['course_search_help'] = 'Type to search for existing courses or create new ones with AI assistance.';
$string['creating_course_ai'] = 'Creating Course with AI';
$string['ai_working'] = 'AI is generating your course structure. This may take a minute...';
$string['course_created_success'] = 'Course created successfully with AI! Redirecting...';

// Block instance capability strings.
$string['mastermind_assistant:addinstance'] = 'Add a new Mastermind Assistant block';
$string['mastermind_assistant:myaddinstance'] = 'Add a new Mastermind Assistant block to the My Moodle page';
$string['mastermind_assistant:view'] = 'View Mastermind Assistant block';

// Mod draft strings.
$string['ai_content_assistant'] = 'AI Content Assistant';
$string['go_to_settings_to_generate'] = 'To use AI content generation, please open the activity settings.';
$string['open_activity_settings'] = 'Open Activity Settings';
$string['draft_page_prompt_new'] = 'Let me draft this {$a} for you!';
$string['draft_page_prompt_edit'] = 'May I help you improve this {$a}?';
$string['draft_quiz_prompt_new'] = 'Let me create questions for this {$a}!';
$string['draft_quiz_prompt_edit'] = 'May I add more questions to this {$a}?';
$string['draft_assign_prompt_new'] = 'Let me draft activity instructions for this {$a}!';
$string['draft_assign_prompt_edit'] = 'May I help improve the instructions for this {$a}?';
$string['content_applied'] = '✓ Content applied successfully!';
$string['questions_added'] = '✓ Questions added successfully!';
$string['instructions_applied'] = '✓ Instructions applied successfully!';
$string['generate_draft'] = 'Generate Draft';
$string['generate_questions'] = 'Generate Questions';
$string['generate_instructions'] = 'Generate Instructions';
$string['generating_content'] = 'Generating Content...';
$string['generating_questions'] = 'Generating Questions...';
$string['analyzing_context'] = 'Analyzing course context and generating content...';
$string['analyzing_quiz_context'] = 'Analyzing quiz topic and generating questions...';
$string['content_generated'] = 'Content Generated Successfully';
$string['questions_generated'] = 'Questions Generated Successfully';
$string['apply_to_page'] = 'Apply to Page';
$string['apply_to_assignment'] = 'Apply to Assignment';
$string['add_to_quiz'] = 'Add to Quiz';
$string['error_generating_content'] = 'Error generating content. Please try again.';
$string['error_generating_questions'] = 'Error generating questions. Please try again.';
$string['questions_count'] = '{$a} questions ready to add';

// Settings page strings.
$string['settings_dashboard_url'] = 'Dashboard URL';
$string['settings_dashboard_url_desc'] = 'The URL of your Mastermind Dashboard instance (e.g. https://mastermindassistant.ai)';
$string['settings_api_key'] = 'API Key';
$string['settings_api_key_desc'] = 'Your Mastermind API key (starts with ma_live_)';
$string['connection_status'] = 'Connection Status';
$string['test_connection'] = 'Test Connection';
$string['test_connection_desc'] = 'Save your settings first, then click Test Connection to verify your API key.';
$string['testing_connection'] = 'Testing connection...';
$string['connection_success'] = 'Connected successfully';
$string['connection_failed'] = 'Connection failed: {$a}';
$string['account_tier'] = 'Tier';
$string['account_status'] = 'Status';
$string['account_usage_requests'] = 'Total Requests';
$string['account_usage_cost'] = 'Total Cost';
$string['settings_not_configured'] = 'Dashboard URL and API key must be configured.';
$string['settings_info_heading'] = 'Account & Support';
$string['settings_register_desc'] = 'Don\'t have an account yet? Register at';
$string['settings_support_desc'] = 'Need help? Contact us at';

// Copy UI strings.
$string['copying_course'] = 'Copying course...';
$string['copy_in_progress'] = 'Please wait while the course is being copied. This may take a moment for large courses.';
$string['copy_success'] = 'Course copied successfully!';
$string['copy_failed'] = 'Failed to copy course. Please try again.';
$string['enrolled_as_teacher'] = 'Enrolled as editing teacher';
$string['update_dates'] = 'Update course dates';
$string['run_analysis'] = 'Run AI Analysis for outdated content';
$string['view_course'] = 'View Course';
$string['all_categories'] = 'All categories';
$string['filter_by_year'] = 'Year';

// Document upload strings.
$string['or_upload_document'] = 'or create from a document';
$string['upload_document_prompt'] = 'Drop a syllabus or curriculum document here';
$string['supported_formats'] = 'PDF, DOCX, or TXT (max 10MB)';
$string['choose_file'] = 'Choose file';
$string['create_from_document'] = 'Create Course from Document';
$string['creating_from_document'] = 'Analyzing document and creating course...';
$string['doc_creation_in_progress'] = 'AI is analyzing your document and generating a course structure. This may take up to 2 minutes for large documents.';
$string['file_too_large'] = 'File is too large. Maximum size is 10MB.';
$string['unsupported_file_type'] = 'Unsupported file type. Please upload a PDF, DOCX, or TXT file.';

// Assignment customization options.
$string['assignment_type_label'] = 'Assignment Type';
$string['select_assignment_type'] = 'Select type (optional)...';
$string['type_essay'] = 'Essay';
$string['type_group_project'] = 'Group Project';
$string['type_presentation'] = 'Presentation';
$string['type_lab_report'] = 'Lab Report';
$string['type_case_study'] = 'Case Study';
$string['type_research_paper'] = 'Research Paper';
$string['academic_level_label'] = 'Academic Level';
$string['select_academic_level'] = 'Select level (optional)...';
$string['level_introductory'] = 'Introductory';
$string['level_intermediate'] = 'Intermediate';
$string['level_advanced'] = 'Advanced';
$string['level_graduate'] = 'Graduate';
$string['quiz_source_doc_divider'] = 'or generate from a document';
$string['quiz_source_doc_prompt'] = 'Drop a source document here';
$string['quiz_source_doc_hint'] = 'PDF, DOCX, or TXT (max 10MB)';
$string['scope_length_label'] = 'Scope / Length';
$string['scope_length_placeholder'] = 'e.g., 1500-2000 words, 10 pages, 15-minute presentation';

// Assignment preview modal.
$string['preview_title'] = 'Preview Generated Instructions';
$string['suggested_title'] = 'Suggested Title';
$string['instructions_preview'] = 'Instructions';
$string['rubric_criteria_label'] = 'Rubric Criteria';
$string['estimated_time_label'] = 'Estimated Time';
$string['key_requirements_label'] = 'Key Requirements';
$string['learning_outcomes_label'] = 'Learning Outcomes';
$string['cancel'] = 'Cancel';
$string['regenerate'] = 'Regenerate';
$string['apply_instructions'] = 'Apply to Assignment';

// Page customization options.
$string['content_type_label'] = 'Content Type';
$string['select_content_type'] = 'Auto-detect from title';
$string['type_lecture_notes'] = 'Lecture Notes';
$string['type_tutorial'] = 'Tutorial / How-To';
$string['type_reference'] = 'Reference Material';
$string['type_case_study_page'] = 'Case Study';
$string['type_overview'] = 'Topic Overview';
$string['target_length_label'] = 'Content Length';
$string['length_brief'] = 'Brief (200-400 words)';
$string['length_standard'] = 'Standard (400-700 words)';
$string['length_comprehensive'] = 'Comprehensive (700-1000 words)';

// Page preview modal.
$string['page_preview_title'] = 'Preview Generated Content';
$string['content_preview'] = 'Page Content';
$string['apply_content'] = 'Apply to Editor';
$string['estimated_reading_time_label'] = 'Reading Time';
$string['content_summary_label'] = 'Summary';
$string['learning_objectives_label'] = 'Learning Objectives';
$string['key_concepts_label'] = 'Key Concepts';

// Quiz customization options.
$string['difficulty_level_label'] = 'Difficulty Level';
$string['difficulty_mixed'] = 'Mixed (recommended)';
$string['difficulty_easy'] = 'Easy';
$string['difficulty_medium'] = 'Medium';
$string['difficulty_hard'] = 'Hard';
$string['question_count_label'] = 'Number of Questions';

// Quiz preview modal.
$string['quiz_preview_title'] = 'Preview Generated Questions';
$string['select_all'] = 'Select All';
$string['deselect_all'] = 'Deselect All';
$string['add_selected_questions'] = 'Add Selected Questions';
$string['questions_selected_suffix'] = 'selected';

// Course preview modal.
$string['course_preview_title'] = 'Preview Course Structure';
$string['course_preview_description'] = 'Description';
$string['course_preview_sections'] = 'Sections';
$string['course_preview_activities'] = 'Activities';
$string['course_preview_create'] = 'Create Course';
$string['course_preview_creating'] = 'Creating course...';
$string['course_preview_section_count'] = '{$a} sections';
$string['course_preview_activity_count'] = '{$a} activities';

// Forum generation strings.
$string['draft_forum_prompt_new'] = 'Let me generate discussion topics for this {$a}!';
$string['draft_forum_prompt_edit'] = 'May I help create discussion prompts for this {$a}?';
$string['generating_forum'] = 'Generating Forum Content...';
$string['generate_forum_content'] = 'Generate Forum Content';
$string['forum_preview_title'] = 'Preview Forum Content';
$string['forum_type_label'] = 'Forum Type';
$string['forum_type_general'] = 'General Forum';
$string['forum_type_single'] = 'Single Discussion';
$string['forum_type_qanda'] = 'Q&A Forum';
$string['forum_type_eachuser'] = 'Each Person Posts One';
$string['discussion_count_label'] = 'Number of Discussions';
$string['forum_introduction_label'] = 'Forum Introduction';
$string['forum_discussions_label'] = 'Discussion Topics';
$string['forum_guidelines_label'] = 'Participation Guidelines';

// Lesson generation strings.
$string['draft_lesson_prompt_new'] = 'Let me create lesson pages for this {$a}!';
$string['draft_lesson_prompt_edit'] = 'May I help improve the content of this {$a}?';
$string['generating_lesson'] = 'Generating Lesson Content...';
$string['generate_lesson_content'] = 'Generate Lesson Content';
$string['lesson_preview_title'] = 'Preview Lesson Content';
$string['page_count_label'] = 'Number of Pages';
$string['lesson_pages_label'] = 'Lesson Pages';

// Glossary generation strings.
$string['draft_glossary_prompt_new'] = 'Let me generate entries for this {$a}!';
$string['draft_glossary_prompt_edit'] = 'May I help add more entries to this {$a}?';
$string['generating_glossary'] = 'Generating Glossary Entries...';
$string['generate_glossary_entries'] = 'Generate Glossary Entries';
$string['glossary_preview_title'] = 'Preview Glossary Entries';
$string['entry_count_label'] = 'Number of Entries';
$string['glossary_description_label'] = 'Glossary Description';
$string['glossary_entries_label'] = 'Entries';

// Book generation strings.
$string['draft_book_prompt_new'] = 'Let me generate chapters for this {$a}!';
$string['draft_book_prompt_edit'] = 'May I help improve the content of this {$a}?';
$string['generating_book'] = 'Generating Book Content...';
$string['generate_book_content'] = 'Generate Book Content';
$string['book_preview_title'] = 'Preview Book Content';
$string['chapter_count_label'] = 'Number of Chapters';
$string['book_chapters_label'] = 'Chapters';

// URL generation strings.
$string['draft_url_prompt_new'] = 'Let me recommend resources for this {$a}!';
$string['draft_url_prompt_edit'] = 'May I suggest alternative resources for this {$a}?';
$string['generating_url'] = 'Finding URL Resources...';
$string['generate_url_recommendations'] = 'Find URL Resources';
$string['url_preview_title'] = 'Recommended URL Resources';
$string['resource_count_label'] = 'Number of Recommendations';
$string['url_topic_summary_label'] = 'Topic Summary';
$string['url_recommendations_label'] = 'Recommended Resources';
$string['apply_url'] = 'Apply Selected URL';

// Direct-action module strings.
$string['go_to_main_page_to_generate'] = 'To use AI content generation, please go to the activity main page.';
$string['open_activity_main_page'] = 'Open Activity Page';
$string['existing_items_count'] = '{$a} existing item(s)';
$string['add_discussions'] = 'Add Discussions';
$string['add_glossary_entries'] = 'Add Entries to Glossary';
$string['add_book_chapters'] = 'Add Chapters to Book';
$string['add_lesson_pages'] = 'Add Pages to Lesson';

// Audit strings.
$string['audit_items_need_updating'] = 'Items that may need updating:';

// Capability strings.
$string['mastermind_assistant:applychanges'] = 'Apply AI-generated changes to courses';

// Metric labels (used in templates and JS).
$string['metric_completion_rate'] = 'Completion Rate';
$string['metric_avg_final_grade'] = 'Avg Final Grade';
$string['metric_dropoff_section'] = 'Drop-off Section';
$string['metric_forum_activity'] = 'Forum Activity';
$string['metric_posts_per_learner'] = 'posts/learner';

// Progress step strings.
$string['ai_analysis_progress'] = 'AI Analysis Progress';
$string['progress_analyzing'] = 'Analyzing course structure and generating recommendations...';
$string['progress_generating_structure'] = 'Generating updated course structure...';
$string['progress_analysis_complete'] = 'Analysis complete!';
$string['processing'] = 'Processing...';
$string['show_detailed_metrics'] = 'Show Detailed Metrics';

// AI policy modal strings.
$string['ai_policy_title'] = 'AI usage policy';
$string['ai_policy_body'] = '<h4>Welcome to the AI-powered features!</h4>
<p>This Artificial Intelligence (AI) feature is based on external Large Language Models (LLM) to improve your learning and teaching experience. Before you start using these AI services, please read this usage policy.</p>
<h5>Accuracy of AI-generated content</h5>
<p>AI can give useful suggestions and information, but its accuracy may vary. You should always double-check the information provided to make sure it\'s accurate, complete, and suitable for your specific situation.</p>
<h5>How your data is processed</h5>
<p>This AI feature uses external Large Language Models (LLM). If you use this feature, any information or personal data you share will be handled according to the privacy policy of those LLMs. We recommend that you read their privacy policy to understand how they will handle your data. Additionally, a record of your interactions with the AI features may be saved in this site.</p>
<p>If you have questions about how your data is processed, please check with your teachers or learning organisation.</p>
<p><strong>By continuing, you acknowledge that you understand and agree to this policy.</strong></p>';
$string['ai_policy_accept_button'] = 'Accept and continue';
$string['ai_policy_accepted_msg'] = 'AI policy accepted. Proceeding with your request...';
$string['ai_policy_declined_msg'] = 'You must accept the AI usage policy to use AI features.';

// Audit finding strings.
$string['audit_past_due_date'] = 'Past due date';
$string['audit_old_year_reference'] = 'Old year reference in';
$string['audit_empty_section'] = 'Empty section';
$string['audit_no_students'] = 'No students enrolled yet';

// Settings JS strings.
$string['settings_save_api_key_first'] = 'Please enter your API key above and save changes first.';

// Success state strings (mod_draft).
$string['success_content_applied'] = 'Content applied successfully!';
$string['success_questions_added'] = 'Questions added successfully!';
$string['success_instructions_applied'] = 'Instructions applied successfully!';
$string['success_forum_applied'] = 'Forum content applied successfully!';
$string['success_lesson_applied'] = 'Lesson content applied successfully!';
$string['success_glossary_applied'] = 'Glossary entries applied successfully!';
$string['success_book_applied'] = 'Book content applied successfully!';
$string['success_url_applied'] = 'URL applied successfully!';

// Connect (simplified setup) flow strings.
$string['connect_title'] = 'Mastermind Assistant';
$string['connect_description'] = 'AI-powered course analysis, content creation, and recommendations — right inside your LMS.';
$string['connect_button'] = 'Connect to Mastermind';
$string['connect_free'] = 'Free to start · No credit card required';
$string['connect_admin_required'] = 'Ask your site administrator to connect Mastermind Assistant.';
$string['connect_connecting'] = 'Connecting...';
$string['connect_waiting'] = 'Waiting for connection...';
$string['connect_popup_blocked'] = 'Pop-up blocked? Open the connect page directly:';
$string['connection_success_redirect'] = 'Mastermind Assistant is now connected! Redirecting...';
$string['invalid_nonce'] = 'Invalid or expired connection request. Please try again.';
$string['invalid_api_key_format'] = 'The API key format is invalid.';

// Privacy strings.
$string['privacy:metadata:preference:ai_policy_accepted'] = 'Whether the user has accepted the AI usage policy.';
$string['privacy:metadata:mastermind_dashboard'] = 'Course and activity data is sent to the Mastermind Dashboard API for AI-powered content generation and analysis.';
$string['privacy:metadata:mastermind_dashboard:coursename'] = 'The name of the course.';
$string['privacy:metadata:mastermind_dashboard:coursedata'] = 'Course structure data including section names and activity names.';
$string['privacy:metadata:mastermind_dashboard:activityname'] = 'The name of the activity being generated.';
$string['privacy:ai_policy_accepted_yes'] = 'The user has accepted the AI usage policy.';
$string['privacy:ai_policy_accepted_no'] = 'The user has not accepted the AI usage policy.';
