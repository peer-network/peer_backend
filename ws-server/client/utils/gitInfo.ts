import { execSync } from 'child_process';

class GitInfo {
  static getBranch(): string {
    try {
      const branch = execSync('git rev-parse --abbrev-ref HEAD', {
        encoding: 'utf-8',
      }).trim();
      return branch;
    } catch (error) {
      console.error('Failed to get git branch:', error);
      return '';
    }
  }

  static getLastCommitId(): string {
    try {
      const commitId = execSync('git rev-parse HEAD', {
        encoding: 'utf-8',
      }).trim();
      return commitId;
    } catch (error) {
      console.error('Failed to get last commit ID:', error);
      return '';
    }
  }
}

export default GitInfo