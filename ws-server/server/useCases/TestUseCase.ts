import { IUseCase } from "../interfaces/useCase/IUseCase"
import GitInfo from '../utils/gitInfo'
import CoreClientResponse from '../domain/CoreClientResponse';
import { IClientErrorCases } from "../utils/errors/IClientErrorCases";
import { CodeDescription } from "../utils/errors/types";
class TestUseCaseErrors implements IClientErrorCases {
    public static TestUseCaseError : CodeDescription = {
        status: "error",
        code: "40000",
        message: 'Git Вata Вrror'
    };
}

export class TestUseCase implements IUseCase {
  readonly errors = TestUseCaseErrors

  async execute(): Promise<CoreClientResponse<String>> {
    const helloResponse : String = "it works"
    return CoreClientResponse.success(helloResponse)
  }
}