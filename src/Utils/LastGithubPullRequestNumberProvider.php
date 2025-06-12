<?php
declare(strict_types=1);

namespace Fawaz\Utils;

class LastGithubPullRequestNumberProvider {

    private static function execute(): string {
        // Run a git log command to get merge commits with "Merge pull request #..."
        $cmd = "git log --merges --grep='Merge pull request #' -i --pretty=format:'%s'";
        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            // echo "Error: Failed to run git log command.\n";
            exit(1);
        }

        // Parse commit messages to find PR numbers
        foreach ($output as $line) {
            if (preg_match('/#(\d+)/', $line, $matches)) {
                $prNumber = $matches[1];
                return $prNumber;
                // echo "Last merged PR number: $prNumber\n";
                exit(0);
            }
        }

        // echo "No merged pull requests found.\n";
        exit(0);
    }

    public static function getValue() {
        return self::execute();
    }
}
