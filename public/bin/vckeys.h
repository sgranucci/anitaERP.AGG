/**
*
*   Vitamin C Multi-User version 1.1
*   Copyright (c) 1985-1990
*   Creative Programming Consultants, Inc.
*   P.O. Box 112097
*   Carrollton, Texas  75006
*   (214) 416-6447
*   ALL RIGHTS RESERVED
*   Last Modification: .3 on 9/1/90 at 00:28:15
*
*   Name            vckeys.h  --  Keyboard Definations          
*
*   Description     
*
*
**/

#define SPACE_BAR 32
#define BTAB 4005
#define ESC 4000
#define RET 4002
#define BACKSP 4001
#define CUR_LEFT 4006
#define CUR_RIGHT 4007
#define CUR_UP 4008
#define CUR_DOWN 4009
#define HOME_KEY 4010
#define END_KEY 4011
#define PGUP 4012
#define PGDN 4013
#define INS_KEY 4014
#define DEL_KEY 4015
#define F1 5000
#define F2 5001
#define F3 5002
#define F4 5003
#define F5 5004
#define F6 5005
#define F7 5006
#define F8 5007
#define F9 5008
#define F10 5009
#define F11 5010
#define F12 5011
#define F13 5012
#define F14 5013
#define F15 5014
#define F16 5015
#define F17 5016
#define F18 5017
#define F19 5018
#define F20 5019
#define F21 5020
#define F22 5021
#define F23 5022
#define F24 5023
#define F25 5024
#define F26 5025
#define F27 5026
#define F28 5027
#define F29 5028
#define F30 5029
#define F31 5030
#define F32 5031
#define F33 5032
#define F34 5033
#define F35 5034
#define F36 5035
#define F37 5036
#define F38 5037
#define F39 5038
#define F40 5039
#define F41 5040
#define F42 5041
#define F43 5042
#define F44 5043
#define F45 5044
#define F46 5045
#define F47 5046
#define F48 5047
#define F49 5048
#define F50 5049

#ifdef UNIX
/* -------------------------  Shift Functions ----------------------------- */
#define SF1 F11
#define SF2 F12
#define SF3 F13
#define SF4 F14
#define SF5 F15
#define SF6 F16
#define SF7 F17
#define SF8 F18
#define SF9 F19
#define SF10 F20

/* -------------------------- Control Functions --------------------------- */
#define CTRL_A  1

#define CF1 F21
#define CF2 F22
#define CF3 F23
#define CF4 F24
#define CF5 F25
#define CF6 F26
#define CF7 F27
#define CF8 F28
#define CF9 F29
#define CF10 F30

/* ---------------------- Control Shift Functions ------------------------- */
#define ACF1 F31
#define ACF2 F32
#define ACF3 F33
#define ACF4 F34
#define ACF5 F35
#define ACF6 F36
#define ACF7 F37
#define ACF8 F38
#define ACF9 F39
#define ACF10 F40

/* ------------------------------  ALT Keys ------------------------------- */
#define ALT_A 5046
#define ALT_B 5047
#define ALT_C 5048
#define ALT_D 5049
#define ALT_E 5050
#define ALT_F 5051
#define ALT_G 5052
#define ALT_H 5053
#define ALT_I 5054
#define ALT_J 5055
#define ALT_K 5056
#define ALT_L 5057
#define ALT_M 5058
#define ALT_N 5059
#define ALT_O 5060
#define ALT_P 5061
#define ALT_Q 5062
#define ALT_R 5063
#define ALT_S 5064
#define ALT_T 5065
#define ALT_U 5066
#define ALT_V 5067
#define ALT_W 5068
#define ALT_X 5069
#define ALT_Y 5070
#define ALT_Z 5071

/* -------------------------  ALT Control Keys ---------------------------- */
#define ALT_INS 48
#define ALT_DEL ESC
#define ALT_HOME 55
#define ALT_END 49
#define ALT_PGUP 57
#define ALT_PGDN 51
#define ALT_CLEFT 52
#define ALT_CRIGHT 54
#define ALT_CUP 56
#define ALT_CDOWN 50
#endif
