<?php
/**
 * Example Usage: Mistral AI Bill Classification
 *
 * This script demonstrates how to use the classifyBillWithAI() function
 * to automatically classify and summarize legislative bills.
 */

require_once __DIR__ . '/../public_html/includes/mistral_ai.php';

// Example 1: Single Bill Classification
echo "=== Example 1: Single Bill Classification ===\n\n";

$result = classifyBillWithAI(
    "Projet de loi relatif à la transition énergétique",
    "Ce projet de loi vise à accélérer la transition vers les énergies renouvelables et à réduire les émissions de carbone de 40% d'ici 2030.",
    "Article 1: La France s'engage à réduire ses émissions de gaz à effet de serre de 40% d'ici 2030. Article 2: Les subventions pour l'installation de panneaux solaires sont augmentées de 25%..."
);

echo "Theme: " . $result['theme'] . "\n";
echo "Summary: " . $result['summary'] . "\n";
echo "Error: " . ($result['error'] ?? 'None') . "\n\n";

// Example 2: Healthcare Bill
echo "=== Example 2: Healthcare Bill ===\n\n";

$result2 = classifyBillWithAI(
    "Proposition de loi sur le remboursement des soins dentaires",
    "Cette proposition vise à améliorer le remboursement des soins dentaires pour tous les citoyens.",
    "Article 1: Le taux de remboursement des soins dentaires est porté à 80% pour les prothèses dentaires..."
);

echo "Theme: " . $result2['theme'] . "\n";
echo "Summary: " . $result2['summary'] . "\n";
echo "Error: " . ($result2['error'] ?? 'None') . "\n\n";

// Example 3: Database Integration
echo "=== Example 3: Database Integration ===\n\n";

// Assuming you have a database connection
// require_once __DIR__ . '/../public_html/config/database.php';

/*
// Get unclassified bills from database
$stmt = $pdo->prepare("
    SELECT id, title, summary, full_text_url
    FROM pending_bills
    WHERE theme = 'Sans catégorie'
    AND ai_processed_at IS NULL
    LIMIT 5
");
$stmt->execute();
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($bills as $bill) {
    echo "Processing bill ID: {$bill['id']} - {$bill['title']}\n";

    // Fetch full text from URL or use existing data
    $fullText = file_get_contents($bill['full_text_url']) ?: '';

    // Classify the bill
    $classification = classifyBillWithAI(
        $bill['title'],
        $bill['summary'],
        $fullText
    );

    if ($classification['error'] === null) {
        // Update database with classification results
        $updateStmt = $pdo->prepare("
            UPDATE pending_bills
            SET theme = :theme,
                ai_summary = :summary,
                ai_processed_at = NOW()
            WHERE id = :id
        ");

        $updateStmt->execute([
            'theme' => $classification['theme'],
            'summary' => $classification['summary'],
            'id' => $bill['id']
        ]);

        echo "  ✓ Classified as: {$classification['theme']}\n";
    } else {
        echo "  ✗ Error: {$classification['error']}\n";
    }

    // Small delay to avoid rate limiting
    usleep(100000); // 100ms
}
*/

echo "Database integration example (commented out - uncomment to use)\n\n";

// Example 4: Batch Processing
echo "=== Example 4: Batch Processing ===\n\n";

$bills = [
    [
        'title' => 'Loi sur la cybersécurité nationale',
        'description' => 'Renforcement de la protection des infrastructures numériques critiques',
        'fullText' => 'Article 1: Création d\'une agence nationale de cybersécurité...'
    ],
    [
        'title' => 'Réforme du code du travail',
        'description' => 'Modernisation des règles sur le télétravail',
        'fullText' => 'Article 1: Le télétravail devient un droit pour tous les salariés...'
    ]
];

$batchResults = classifyBillsBatch($bills);

foreach ($batchResults as $index => $result) {
    echo "Bill " . ($index + 1) . ":\n";
    echo "  Theme: {$result['theme']}\n";
    echo "  Summary: {$result['summary']}\n";
    echo "  Error: " . ($result['error'] ?? 'None') . "\n\n";
}

echo "=== All Examples Completed ===\n";
