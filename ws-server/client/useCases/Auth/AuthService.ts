import { IPeerBackendAuthRepository } from "../../interfaces/repos/IPeerBackendAuthRepository";
import { IBackendRepository } from "../../interfaces/repos/IPeerBackendRepository";
import { IUseCase } from "../../interfaces/useCase/IUseCase"
import {clientManager} from "../../app/api/client/client";
import { CodeDescription } from "../../utils/errors/types";
import CoreClientResponse from "../../domain/CoreClientResponse";
import { IClientException } from "../../utils/errors/IClientException";
import { IClientErrorCases } from "../../utils/errors/IClientErrorCases";
import { IAuthServiceValidator } from "./validation/IAuthServiceValidator";


class AuthServiceErrors implements IClientErrorCases {
    public static TokenUpdateFailed : CodeDescription = {
      code: "40000",
      message: 'Token Update Failed'
    };
    public static NoResponseFromPeerBackend : CodeDescription = {
      code: "40000",
      message: 'No Response From Peer Backend'
    };
    public static TokenIsNull : CodeDescription = {
      code: "40000",
      message: 'Access Token is NULL'
    };
}

export class AuthService implements IUseCase {
  errors = AuthServiceErrors;

  constructor(
    private backendRepo: IBackendRepository,
    private authRepo: IPeerBackendAuthRepository,
    private validator: IAuthServiceValidator,
  ) {}

  async execute(): Promise<CoreClientResponse<any>> {
    try {
      const helloData = await this.backendRepo.getHelloData()
      if (!helloData) {
        return CoreClientResponse.error(this.errors.NoResponseFromPeerBackend)
      }
      const userId = helloData.currentuserid

      if (!userId) {
        const loginData = await this.authRepo.getTokenData()
        this.validator.validateLoginResponse(loginData)

        clientManager.updateAccessToken(loginData.accessToken!)
      }
      return CoreClientResponse.success()

    } catch(e) {
      const clientException = e as IClientException
      
      if (clientException && clientException.code) {
        return CoreClientResponse.error(clientException)
      }
      const exception = e as Error
      return CoreClientResponse.error(this.errors.TokenUpdateFailed,exception.name + "  " + exception.message)
    }
  }
}

