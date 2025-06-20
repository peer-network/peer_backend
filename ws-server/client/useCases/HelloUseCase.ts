import { IUseCase } from "../interfaces/useCase/IUseCase"
import GitInfo from '../utils/gitInfo'
import { GitData } from '../infrastructure/gql/generated-types/server/types-server';
import CoreClientResponse from '../domain/CoreClientResponse';
import { IClientErrorCases } from "../utils/errors/IClientErrorCases";
import { CodeDescription } from "../utils/errors/types";

class HelloUseCaseErrors implements IClientErrorCases {
    public static GitDataError : CodeDescription = {
        code: "40000",
        message: 'Git Вata Вrror'
    };
}

export class HelloUseCase implements IUseCase {
  readonly errors = HelloUseCaseErrors

  async execute(): Promise<CoreClientResponse<GitData>> {
    const helloResponse : GitData = {
        gitCommitId : GitInfo.getLastCommitId(),
        gitBranch : GitInfo.getBranch()
    }
    if (!helloResponse.gitBranch || !helloResponse.gitCommitId) {
      return CoreClientResponse.error(this.errors.GitDataError)
    }
    return CoreClientResponse.success(helloResponse)
  }
}