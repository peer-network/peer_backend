import { CodeDescription } from '../utils/errors';
import { IClientException } from '../utils/errors/IClientException';
import { logger } from '../utils/logger';

export default class CoreClientResponse<TData> {
  
  public readonly responseCode: string;
  public readonly message: string;
  public readonly data: TData | null;  
//   public readonly timestamp: number;
  
  static successCodeDescription : CodeDescription = {
    code: "10000",
    message: "success"
  }

  constructor(responseCode: string, responseMessage: string, data?: TData) {
    // logger.error(error.message, detailsMessage)
    this.responseCode      = responseCode;
    this.message   = responseMessage;
    this.data      = data || null;
    // this.timestamp = Date.now();
  }
  
  public static success<TData>(data?: TData,optionalMessage? : string, codeDescription: CodeDescription = this.successCodeDescription): CoreClientResponse<TData> {
    const resultCode: string = codeDescription.code;
    const resultMessage: string = optionalMessage || codeDescription.message;
    logger.info(
      resultCode,
      resultMessage
    )
    return new CoreClientResponse(resultCode, resultMessage, data);
  }

  public static error<TData>(clientException: IClientException , optionalMessage? : string,data?: TData): CoreClientResponse<TData>;
  public static error<TData>(codeDescription: CodeDescription , optionalMessage? : string,data?: TData): CoreClientResponse<TData>;
  public static error<TData>(error: CodeDescription | IClientException , optionalMessage : string = "",data?: TData): CoreClientResponse<TData> {
    const resultCode: string = error.code;
    const resultMessage: string = error.message + " " + optionalMessage;
    logger.error(
      resultCode,
      resultMessage
    )
    return new CoreClientResponse(resultCode, resultMessage, data);
  }
}