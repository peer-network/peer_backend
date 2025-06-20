import { IBackendRepository } from "../../interfaces/repos/IPeerBackendRepository";
import { IPeerBackendAuthRepository } from "../../interfaces/repos/IPeerBackendAuthRepository";
import { ISolanaRepository } from "../../interfaces/repos/ISolanaRepository";
import { IUseCase } from "../../interfaces/useCase/IUseCase"
import { CodeDescription } from "../../utils/errors/types";
import { AuthService } from "../Auth/AuthService";
import CoreClientResponse from "../../domain/CoreClientResponse";
import { ClientErrorCases } from "../../utils/errors/IClientErrorCases";
// import dailyGemsResultsResponse from "../../domain/mock/dailyGemsResultsResponse.json"
import { IMintUseCaseValidator } from "./validation/IMintUseCaseValidator";
import { ClientException } from "../../utils/errors/IClientException";
import AuthServiceValidatorImplZod from "../../useCases/Auth/validation/AuthServiceValidatorImplZod";
import {ClientTypes} from "../../domain/GemsResultsData"
import {DistributionCalculator} from "../Mint/distributionCalculation/DistributionCalculator"
import { Status } from "../../handlers/solanaProvider/SolanaProviderResponse";

class MintUseCaseErrors extends ClientErrorCases {
  public static filedToMint : CodeDescription = {
    code: "40000",
    message: 'Failed to mint'
  };
  public static filedToAuthorise : CodeDescription = {
    code: "40000",
    message: 'Failed to authorise'
  };
}

export class MintUseCase implements IUseCase {
  readonly errors = MintUseCaseErrors

  constructor(
    private backendRepo: IBackendRepository,
    private authRepo: IPeerBackendAuthRepository,
    private solanaRepo: ISolanaRepository,
    private validator: IMintUseCaseValidator,
  ) {}

  async execute(day : ClientTypes.DayFilterType): Promise<CoreClientResponse<any>> {
      try {
        await this.authorise()
    
        const gemsData = await this.backendRepo.getDailyGemsResultData(day)
        this.validator.validateGemsResults(gemsData)
        
        const distribution = DistributionCalculator.calculate(gemsData)

        const result = await this.solanaRepo.mint()
        
        if (result.isFailure()) {
          return CoreClientResponse.error(result.error)
        } else {
          return CoreClientResponse.success()
        }
      } catch(e) {

        const error = e as Error
        const clientException = error as ClientException
        
        if (!clientException || !clientException.code) {
          return CoreClientResponse.error(this.errors.filedToMint, error.name + ": " + error.message)
        }
        return CoreClientResponse.error(clientException)
      }
    }
  
  async authorise() {
    let response = await new AuthService(
      this.backendRepo,
      this.authRepo,
      new AuthServiceValidatorImplZod()
    ).execute()
    
    if (response.responseCode != CoreClientResponse.successCodeDescription.code) {
      throw new ClientException(MintUseCaseErrors.filedToAuthorise)
    }
  }
}