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
 * German language strings for Mastermind Assistant
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Sprachstrings fuer den Mastermind-Assistenten-Block (Deutsch).

$string['pluginname'] = 'Mastermind-Assistent';
$string['insights_title'] = 'Erkenntnisse';
$string['coursecompletionrate'] = 'Kursabschlussrate: {$a}%';
$string['averagefinalgrade'] = 'Durchschnittliche Endnote: {$a}%';
$string['dropoffsection'] = 'Absprungabschnitt: {$a}';
$string['forumactivity'] = 'Forenaktivitaet: {$a} Beitraege/Teilnehmer';
$string['averagetimeincourse'] = 'Durchschnittliche Kursdauer: {$a}';
$string['learnersatisfaction'] = 'Zufriedenheit der Lernenden: {$a}/5';
$string['unknown'] = 'Unbekannt';
$string['no_activity'] = 'Keine Aktivitaet';
$string['last_activity'] = 'Letzte Aktivitaet';
$string['resources'] = 'Ressourcen';
$string['no_feedback'] = 'Keine Feedback-Aktivitaet';
$string['notapplicable'] = 'Kurserkenntnisse sind auf dieser Seite nicht verfuegbar.';
$string['nav_manage_courses'] = 'Kurse und Kategorien verwalten';
$string['nav_search_courses'] = 'Kurse suchen';
$string['nav_browse_categories'] = 'Kategorien durchsuchen';
$string['getrecommendations'] = 'Empfehlungen abrufen';
$string['errorgetcoursedata'] = 'Fehler beim Abrufen der Kursdaten';
$string['openai_connected'] = 'OpenAI-Verbindung erfolgreich';
$string['openai_failed'] = 'OpenAI-Verbindung fehlgeschlagen';
$string['getting_ai_recommendations'] = 'KI-Empfehlungen werden abgerufen...';

// Icon-Strings
$string['openai_success_icon'] = "\u2713";
$string['openai_warning_icon'] = "\u26A0";
$string['openai_error_icon'] = "\u2717";

// Kurssuche
$string['search_course_placeholder'] = 'Kursname eingeben...';
$string['searching'] = 'Kurse werden gesucht...';
$string['course_search_help'] = 'Geben Sie einen Suchbegriff ein, um bestehende Kurse zu finden oder neue mit KI-Unterstuetzung zu erstellen.';
$string['creating_course_ai'] = 'Kurs mit KI erstellen';
$string['ai_working'] = 'Die KI erstellt Ihre Kursstruktur. Dies kann einen Moment dauern...';
$string['course_created_success'] = 'Kurs erfolgreich mit KI erstellt! Weiterleitung...';

// Block-Berechtigungen
$string['mastermind_assistant:addinstance'] = 'Neuen Mastermind-Assistenten-Block hinzufuegen';
$string['mastermind_assistant:myaddinstance'] = 'Neuen Mastermind-Assistenten-Block zur Mein-Moodle-Seite hinzufuegen';
$string['mastermind_assistant:view'] = 'Mastermind-Assistenten-Block anzeigen';

// Mod-Draft-Strings
$string['ai_content_assistant'] = 'KI-Inhaltsassistent';
$string['go_to_settings_to_generate'] = 'Um die KI-Inhaltserstellung zu nutzen, oeffnen Sie bitte die Aktivitaetseinstellungen.';
$string['open_activity_settings'] = 'Aktivitaetseinstellungen oeffnen';
$string['draft_page_prompt_new'] = 'Ich erstelle einen Entwurf fuer diese(s) {$a}!';
$string['draft_page_prompt_edit'] = 'Kann ich Ihnen helfen, diese(s) {$a} zu verbessern?';
$string['draft_quiz_prompt_new'] = 'Ich erstelle Fragen fuer diese(s) {$a}!';
$string['draft_quiz_prompt_edit'] = 'Soll ich weitere Fragen zu diesem/dieser {$a} hinzufuegen?';
$string['draft_assign_prompt_new'] = 'Ich erstelle Anweisungen fuer diese(s) {$a}!';
$string['draft_assign_prompt_edit'] = 'Kann ich helfen, die Anweisungen fuer diese(s) {$a} zu verbessern?';
$string['content_applied'] = 'Inhalt erfolgreich angewendet!';
$string['questions_added'] = 'Fragen erfolgreich hinzugefuegt!';
$string['instructions_applied'] = 'Anweisungen erfolgreich angewendet!';
$string['generate_draft'] = 'Entwurf erstellen';
$string['generate_questions'] = 'Fragen erstellen';
$string['generate_instructions'] = 'Anweisungen erstellen';
$string['generating_content'] = 'Inhalt wird erstellt...';
$string['generating_questions'] = 'Fragen werden erstellt...';
$string['analyzing_context'] = 'Kurskontext wird analysiert und Inhalt erstellt...';
$string['analyzing_quiz_context'] = 'Testthema wird analysiert und Fragen erstellt...';
$string['content_generated'] = 'Inhalt erfolgreich erstellt';
$string['questions_generated'] = 'Fragen erfolgreich erstellt';
$string['apply_to_page'] = 'Auf Seite anwenden';
$string['apply_to_assignment'] = 'Auf Aufgabe anwenden';
$string['add_to_quiz'] = 'Zum Test hinzufuegen';
$string['error_generating_content'] = 'Fehler bei der Inhaltserstellung. Bitte versuchen Sie es erneut.';
$string['error_generating_questions'] = 'Fehler bei der Fragenerstellung. Bitte versuchen Sie es erneut.';
$string['questions_count'] = '{$a} Fragen bereit zum Hinzufuegen';

// Einstellungen
$string['settings_dashboard_url'] = 'Dashboard-URL';
$string['settings_dashboard_url_desc'] = 'Die URL Ihrer Mastermind-Dashboard-Instanz (z.B. https://mastermindassistant.ai)';
$string['settings_api_key'] = 'API-Schluessel';
$string['settings_api_key_desc'] = 'Ihr Mastermind-API-Schluessel (beginnt mit ma_live_)';
$string['connection_status'] = 'Verbindungsstatus';
$string['test_connection'] = 'Verbindung testen';
$string['test_connection_desc'] = 'Speichern Sie zuerst Ihre Einstellungen und klicken Sie dann auf Verbindung testen, um Ihren API-Schluessel zu ueberpruefen.';
$string['testing_connection'] = 'Verbindung wird getestet...';
$string['connection_success'] = 'Verbindung erfolgreich hergestellt';
$string['connection_failed'] = 'Verbindung fehlgeschlagen: {$a}';
$string['account_tier'] = 'Tarif';
$string['account_status'] = 'Status';
$string['account_usage_requests'] = 'Anfragen gesamt';
$string['account_usage_cost'] = 'Kosten gesamt';
$string['settings_not_configured'] = 'Dashboard-URL und API-Schluessel muessen konfiguriert werden.';
$string['settings_info_heading'] = 'Konto & Support';
$string['settings_register_desc'] = 'Noch kein Konto? Registrieren Sie sich unter';
$string['settings_support_desc'] = 'Brauchen Sie Hilfe? Kontaktieren Sie uns unter';

// Kopier-UI
$string['copying_course'] = 'Kurs wird kopiert...';
$string['copy_in_progress'] = 'Bitte warten Sie, waehrend der Kurs kopiert wird. Bei grossen Kursen kann dies einen Moment dauern.';
$string['copy_success'] = 'Kurs erfolgreich kopiert!';
$string['copy_failed'] = 'Kurs konnte nicht kopiert werden. Bitte versuchen Sie es erneut.';
$string['enrolled_as_teacher'] = 'Als Trainer/in eingeschrieben';
$string['update_dates'] = 'Kursdaten aktualisieren';
$string['run_analysis'] = 'KI-Analyse fuer veraltete Inhalte starten';
$string['view_course'] = 'Kurs anzeigen';
$string['all_categories'] = 'Alle Kategorien';
$string['filter_by_year'] = 'Jahr';

// Dokument-Upload
$string['or_upload_document'] = 'oder aus einem Dokument erstellen';
$string['upload_document_prompt'] = 'Lehrplan oder Curriculum-Dokument hier ablegen';
$string['supported_formats'] = 'PDF, DOCX oder TXT (max. 10 MB)';
$string['choose_file'] = 'Datei auswaehlen';
$string['create_from_document'] = 'Kurs aus Dokument erstellen';
$string['creating_from_document'] = 'Dokument wird analysiert und Kurs erstellt...';
$string['doc_creation_in_progress'] = 'Die KI analysiert Ihr Dokument und erstellt eine Kursstruktur. Bei grossen Dokumenten kann dies bis zu 2 Minuten dauern.';
$string['file_too_large'] = 'Die Datei ist zu gross. Maximale Groesse: 10 MB.';
$string['unsupported_file_type'] = 'Nicht unterstuetzter Dateityp. Bitte laden Sie eine PDF-, DOCX- oder TXT-Datei hoch.';

// Aufgaben-Anpassungsoptionen
$string['assignment_type_label'] = 'Aufgabentyp';
$string['select_assignment_type'] = 'Typ auswaehlen (optional)...';
$string['type_essay'] = 'Aufsatz';
$string['type_group_project'] = 'Gruppenprojekt';
$string['type_presentation'] = 'Praesentation';
$string['type_lab_report'] = 'Laborbericht';
$string['type_case_study'] = 'Fallstudie';
$string['type_research_paper'] = 'Forschungsarbeit';
$string['academic_level_label'] = 'Akademisches Niveau';
$string['select_academic_level'] = 'Niveau auswaehlen (optional)...';
$string['level_introductory'] = 'Einfuehrung';
$string['level_intermediate'] = 'Mittelstufe';
$string['level_advanced'] = 'Fortgeschritten';
$string['level_graduate'] = 'Master/Promotion';
$string['scope_length_label'] = 'Umfang / Laenge';
$string['scope_length_placeholder'] = 'z.B. 1500-2000 Woerter, 10 Seiten, 15-minuetige Praesentation';

// Aufgaben-Vorschau-Modal
$string['preview_title'] = 'Vorschau der erstellten Anweisungen';
$string['suggested_title'] = 'Vorgeschlagener Titel';
$string['instructions_preview'] = 'Anweisungen';
$string['rubric_criteria_label'] = 'Bewertungskriterien';
$string['estimated_time_label'] = 'Geschaetzte Zeit';
$string['key_requirements_label'] = 'Hauptanforderungen';
$string['learning_outcomes_label'] = 'Lernziele';
$string['cancel'] = 'Abbrechen';
$string['regenerate'] = 'Neu erstellen';
$string['apply_instructions'] = 'Auf Aufgabe anwenden';

// Seiten-Anpassungsoptionen
$string['content_type_label'] = 'Inhaltstyp';
$string['select_content_type'] = 'Automatisch aus Titel erkennen';
$string['type_lecture_notes'] = 'Vorlesungsnotizen';
$string['type_tutorial'] = 'Anleitung / How-To';
$string['type_reference'] = 'Referenzmaterial';
$string['type_case_study_page'] = 'Fallstudie';
$string['type_overview'] = 'Themenuebersicht';
$string['target_length_label'] = 'Inhaltslaenge';
$string['length_brief'] = 'Kurz (200-400 Woerter)';
$string['length_standard'] = 'Standard (400-700 Woerter)';
$string['length_comprehensive'] = 'Umfassend (700-1000 Woerter)';

// Seiten-Vorschau-Modal
$string['page_preview_title'] = 'Vorschau des erstellten Inhalts';
$string['content_preview'] = 'Seiteninhalt';
$string['apply_content'] = 'Im Editor anwenden';
$string['estimated_reading_time_label'] = 'Lesezeit';
$string['content_summary_label'] = 'Zusammenfassung';
$string['learning_objectives_label'] = 'Lernziele';
$string['key_concepts_label'] = 'Schluesselbegriffe';

// Test-Anpassungsoptionen
$string['difficulty_level_label'] = 'Schwierigkeitsgrad';
$string['difficulty_mixed'] = 'Gemischt (empfohlen)';
$string['difficulty_easy'] = 'Einfach';
$string['difficulty_medium'] = 'Mittel';
$string['difficulty_hard'] = 'Schwer';
$string['question_count_label'] = 'Anzahl der Fragen';

// Test-Vorschau-Modal
$string['quiz_preview_title'] = 'Vorschau der erstellten Fragen';
$string['select_all'] = 'Alle auswaehlen';
$string['deselect_all'] = 'Alle abwaehlen';
$string['add_selected_questions'] = 'Ausgewaehlte Fragen hinzufuegen';
$string['questions_selected_suffix'] = 'ausgewaehlt';

// Kurs-Vorschau-Modal
$string['course_preview_title'] = 'Vorschau der Kursstruktur';
$string['course_preview_description'] = 'Beschreibung';
$string['course_preview_sections'] = 'Abschnitte';
$string['course_preview_activities'] = 'Aktivitaeten';
$string['course_preview_create'] = 'Kurs erstellen';
$string['course_preview_creating'] = 'Kurs wird erstellt...';
$string['course_preview_section_count'] = '{$a} Abschnitte';
$string['course_preview_activity_count'] = '{$a} Aktivitaeten';

// Forum-Erstellung
$string['draft_forum_prompt_new'] = 'Ich erstelle Diskussionsthemen fuer dieses {$a}!';
$string['draft_forum_prompt_edit'] = 'Kann ich Diskussionsanregungen fuer dieses {$a} erstellen?';
$string['generating_forum'] = 'Foreninhalte werden erstellt...';
$string['generate_forum_content'] = 'Foreninhalte erstellen';
$string['forum_preview_title'] = 'Vorschau der Foreninhalte';
$string['forum_type_label'] = 'Forentyp';
$string['forum_type_general'] = 'Allgemeines Forum';
$string['forum_type_single'] = 'Einzeldiskussion';
$string['forum_type_qanda'] = 'Frage-Antwort-Forum';
$string['forum_type_eachuser'] = 'Jeder schreibt einen Beitrag';
$string['discussion_count_label'] = 'Anzahl der Diskussionen';
$string['forum_introduction_label'] = 'Foreneinleitung';
$string['forum_discussions_label'] = 'Diskussionsthemen';
$string['forum_guidelines_label'] = 'Teilnahmerichtlinien';

// Lektion-Erstellung
$string['draft_lesson_prompt_new'] = 'Ich erstelle Lektionsseiten fuer diese {$a}!';
$string['draft_lesson_prompt_edit'] = 'Kann ich helfen, den Inhalt dieser {$a} zu verbessern?';
$string['generating_lesson'] = 'Lektionsinhalte werden erstellt...';
$string['generate_lesson_content'] = 'Lektionsinhalte erstellen';
$string['lesson_preview_title'] = 'Vorschau der Lektionsinhalte';
$string['page_count_label'] = 'Anzahl der Seiten';
$string['lesson_pages_label'] = 'Lektionsseiten';

// Glossar-Erstellung
$string['draft_glossary_prompt_new'] = 'Ich erstelle Eintraege fuer dieses {$a}!';
$string['draft_glossary_prompt_edit'] = 'Kann ich weitere Eintraege zu diesem {$a} hinzufuegen?';
$string['generating_glossary'] = 'Glossareintraege werden erstellt...';
$string['generate_glossary_entries'] = 'Glossareintraege erstellen';
$string['glossary_preview_title'] = 'Vorschau der Glossareintraege';
$string['entry_count_label'] = 'Anzahl der Eintraege';
$string['glossary_description_label'] = 'Glossarbeschreibung';
$string['glossary_entries_label'] = 'Eintraege';

// Buch-Erstellung
$string['draft_book_prompt_new'] = 'Ich erstelle Kapitel fuer dieses {$a}!';
$string['draft_book_prompt_edit'] = 'Kann ich helfen, den Inhalt dieses {$a} zu verbessern?';
$string['generating_book'] = 'Buchinhalte werden erstellt...';
$string['generate_book_content'] = 'Buchinhalte erstellen';
$string['book_preview_title'] = 'Vorschau der Buchinhalte';
$string['chapter_count_label'] = 'Anzahl der Kapitel';
$string['book_chapters_label'] = 'Kapitel';

// URL-Erstellung
$string['draft_url_prompt_new'] = 'Ich empfehle Ressourcen fuer diese(s) {$a}!';
$string['draft_url_prompt_edit'] = 'Soll ich alternative Ressourcen fuer diese(s) {$a} vorschlagen?';
$string['generating_url'] = 'URL-Ressourcen werden gesucht...';
$string['generate_url_recommendations'] = 'URL-Ressourcen finden';
$string['url_preview_title'] = 'Empfohlene URL-Ressourcen';
$string['resource_count_label'] = 'Anzahl der Empfehlungen';
$string['url_topic_summary_label'] = 'Themenzusammenfassung';
$string['url_recommendations_label'] = 'Empfohlene Ressourcen';
$string['apply_url'] = 'Ausgewaehlte URL anwenden';

// Direkt-Aktions-Modul-Strings
$string['go_to_main_page_to_generate'] = 'Um die KI-Inhaltserstellung zu nutzen, gehen Sie bitte zur Hauptseite der Aktivitaet.';
$string['open_activity_main_page'] = 'Aktivitaetsseite oeffnen';
$string['existing_items_count'] = '{$a} vorhandene(r) Eintrag/Eintraege';
$string['add_discussions'] = 'Diskussionen hinzufuegen';
$string['add_glossary_entries'] = 'Eintraege zum Glossar hinzufuegen';
$string['add_book_chapters'] = 'Kapitel zum Buch hinzufuegen';
$string['add_lesson_pages'] = 'Seiten zur Lektion hinzufuegen';

// Pruefungsstrings.
$string['audit_items_need_updating'] = 'Elemente, die moeglicherweise aktualisiert werden muessen:';

// Berechtigungen.
$string['mastermind_assistant:applychanges'] = 'KI-generierte Aenderungen auf Kurse anwenden';

// Metrik-Beschriftungen.
$string['metric_completion_rate'] = 'Abschlussrate';
$string['metric_avg_final_grade'] = 'Durchschnittliche Endnote';
$string['metric_dropoff_section'] = 'Abbruch-Abschnitt';
$string['metric_forum_activity'] = 'Forumsaktivitaet';
$string['metric_posts_per_learner'] = 'Beitraege/Lernende';

// Fortschrittsanzeige.
$string['ai_analysis_progress'] = 'KI-Analyse Fortschritt';
$string['progress_analyzing'] = 'Kursstruktur wird analysiert und Empfehlungen werden generiert...';
$string['progress_generating_structure'] = 'Aktualisierte Kursstruktur wird erstellt...';
$string['progress_analysis_complete'] = 'Analyse abgeschlossen!';
$string['processing'] = 'Verarbeitung...';
$string['show_detailed_metrics'] = 'Detaillierte Metriken anzeigen';

// KI-Richtlinien-Modal.
$string['ai_policy_title'] = 'KI-Nutzungsrichtlinie';
$string['ai_policy_body'] = '<h4>Willkommen bei den KI-gestuetzten Funktionen!</h4>
<p>Diese Kuenstliche-Intelligenz-Funktion (KI) basiert auf externen grossen Sprachmodellen (LLM), um Ihre Lern- und Lehrerfahrung zu verbessern. Bevor Sie diese KI-Dienste nutzen, lesen Sie bitte diese Nutzungsrichtlinie.</p>
<h5>Genauigkeit von KI-generierten Inhalten</h5>
<p>KI kann nuetzliche Vorschlaege und Informationen liefern, aber ihre Genauigkeit kann variieren. Sie sollten die bereitgestellten Informationen immer ueberpruefen, um sicherzustellen, dass sie korrekt, vollstaendig und fuer Ihre spezifische Situation geeignet sind.</p>
<h5>Wie Ihre Daten verarbeitet werden</h5>
<p>Diese KI-Funktion verwendet externe grosse Sprachmodelle (LLM). Wenn Sie diese Funktion nutzen, werden alle Informationen oder persoenlichen Daten, die Sie teilen, gemaess der Datenschutzrichtlinie dieser LLMs behandelt. Wir empfehlen Ihnen, deren Datenschutzrichtlinie zu lesen, um zu verstehen, wie sie mit Ihren Daten umgehen. Zusaetzlich kann eine Aufzeichnung Ihrer Interaktionen mit den KI-Funktionen auf dieser Website gespeichert werden.</p>
<p>Wenn Sie Fragen zur Verarbeitung Ihrer Daten haben, wenden Sie sich bitte an Ihre Lehrenden oder Bildungseinrichtung.</p>
<p><strong>Durch Fortfahren bestaetigen Sie, dass Sie diese Richtlinie verstehen und ihr zustimmen.</strong></p>';
$string['ai_policy_accept_button'] = 'Akzeptieren und fortfahren';
$string['ai_policy_accepted_msg'] = 'KI-Richtlinie akzeptiert. Ihre Anfrage wird verarbeitet...';
$string['ai_policy_declined_msg'] = 'Sie muessen die KI-Nutzungsrichtlinie akzeptieren, um KI-Funktionen zu nutzen.';

// Audit-Strings.
$string['audit_past_due_date'] = 'Ueberfaelliges Faelligkeitsdatum';
$string['audit_old_year_reference'] = 'Alte Jahresreferenz in';
$string['audit_empty_section'] = 'Leerer Abschnitt';
$string['audit_no_students'] = 'Noch keine Studierenden eingeschrieben';

// Einstellungs-JS-Strings.
$string['settings_save_api_key_first'] = 'Bitte geben Sie oben Ihren API-Schluessel ein und speichern Sie die Aenderungen zuerst.';

// Erfolgsmeldungen (mod_draft).
$string['success_content_applied'] = 'Inhalt erfolgreich angewendet!';
$string['success_questions_added'] = 'Fragen erfolgreich hinzugefuegt!';
$string['success_instructions_applied'] = 'Anweisungen erfolgreich angewendet!';
$string['success_forum_applied'] = 'Forumsinhalte erfolgreich angewendet!';
$string['success_lesson_applied'] = 'Lektionsinhalte erfolgreich angewendet!';
$string['success_glossary_applied'] = 'Glossareintraege erfolgreich angewendet!';
$string['success_book_applied'] = 'Buchinhalte erfolgreich angewendet!';
$string['success_url_applied'] = 'URL erfolgreich angewendet!';

// Datenschutz.
$string['privacy:metadata:preference:ai_policy_accepted'] = 'Ob der Benutzer die KI-Nutzungsrichtlinie akzeptiert hat.';
$string['privacy:metadata:mastermind_dashboard'] = 'Kurs- und Aktivitaetsdaten werden zur KI-gestuetzten Inhaltserstellung und Analyse an die Mastermind-Dashboard-API gesendet.';
$string['privacy:metadata:mastermind_dashboard:coursename'] = 'Der Name des Kurses.';
$string['privacy:metadata:mastermind_dashboard:coursedata'] = 'Kursstrukturdaten einschliesslich Abschnittsnamen und Aktivitaetsnamen.';
$string['privacy:metadata:mastermind_dashboard:activityname'] = 'Der Name der zu erstellenden Aktivitaet.';
$string['privacy:ai_policy_accepted_yes'] = 'Der Benutzer hat die KI-Nutzungsrichtlinie akzeptiert.';
$string['privacy:ai_policy_accepted_no'] = 'Der Benutzer hat die KI-Nutzungsrichtlinie nicht akzeptiert.';
