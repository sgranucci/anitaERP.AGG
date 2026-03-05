/**
*
*   Vitamin C Multi-User version 1.1
*   Copyright (c) 1985-1990
*   Creative Programming Consultants, Inc.
*   P.O. Box 112097
*   Carrollton, Texas  75006
*   (214) 416-6447
*   ALL RIGHTS RESERVED
*   Last Modification: .4 on 9/2/90 at 14:44:27
*
*   Name            vcdef.h  --  Vitamin C Defines
*
*   Description     
*
*
**/

/*------------------------------------------------------------------*/
/*--------------------------- MAGIC NUMBERS ------------------------*/
/*------------------------------------------------------------------*/

#define GETTBLMAGIC  902
#define GETNODEMAGIC 105
#define FLDNODEMAGIC 791
#define MENUMAGIC    619
#define MSTYLEMAGIC 209
#define MITEMMAGIC 6809
#define SELITEMMAGIC  4692
#define PROKEYMAGIC 3443

/*------------------------------------------------------------------*/
/*------------------------------------------------------------------*/
/*------------------------------------------------------------------*/

#define AGET 1
#define NAGET 2
#define SAY 3
#define ON 1
#define OFF 0
#define TOGGLE 2
#define BELL 7
#define TABSIZE 8
#define SPACE ' '
#define GOOD 0
#define BAD 1
#define SAVESCRN 1
#define CLRSCRN 0
#define CLOSE 1
#define NOCLOSE 0
#define COMBO 2
#define DOUBLE 0
#define SINGLE 1

#ifdef PRIMOS
#define AT_ON 1
#define AT_OFF 2
#else
#ifdef NOUNSIGNED
#define AT_ON -128
#define AT_OFF -127
#else
#define AT_ON 255
#define AT_OFF 254
#endif
#endif

#define BIT0 1
#define BIT1 2
#define BIT2 4
#define BIT3 8
#define BIT4 16
#define BIT5 32
#define BIT6 64
#define BIT7 128
#define BIT8 256
#define BIT9 512
#define BIT10 1024
#define BIT11 2048
#define BIT12 4096
#define BIT13 8192
#define BIT14 16384
#define BIT15 32768

#define GET BIT1
#define SET BIT2

/*------------------------------------------------------------------*/
/*-------------------------Terminal Defines-------------------------*/
/*------------------------------------------------------------------*/

#define GRAPH_ATTR 0x88
#define INTEN_ATTR 0x08
#define BLINK_ATTR 0x80
#define BACKGROUND(at) ((at & 0x70) >> 4)
#define FOREGROUND(at) ((at & 0x07))
#define UNDER_ATTR 0x01
#define NORM_ATTR 0x07
#define INVER_ATTR 0x70

#define NORMCURSOR 3
#define ALTCURSOR 4
#define OFFCURSOR 0x2000
#define ONCURSOR ~OFFCURSOR

/*------------------------------------------------------------------*/
/*-------------------------Debug Defines----------------------------*/
/*------------------------------------------------------------------*/
#ifdef VCDEBUG
#define CONTINUE BIT0
#define STOP BIT1
#endif


/*------------------------------------------------------------------*/
/*----------------------Window Control Byte Values------------------*/
/*------------------------------------------------------------------*/

#define WOPEN 1      /* Tell if a Window is open                    */
#define BORDER 2     /* Print Border around the Window              */
#define ACTIVE 4     /* Active means Display on Screen              */
#define CURSOR 8     /* Put a Cursor on the next position to print  */
#define SCROLL 16    /* Scroll the window after last line, last col.*/
#define BD1 0        /* Use Border Number 1                         */
#define BD2 32       /* Use Border Number 2                         */
#define BD3 64       /* Use Border Number 3                         */
#define BD4 96       /* Use Border Number 4 (not defined)           */
#define COOKED 128   /* Convert Control Codes in Windows            */
#define SCROLLBAR 256 /* Scroll Bar                                 */
#define MSGLINE 384   /* Custom Open                                */
#define WOPENBACK 512 /* Open window active by not selected         */
#define STATUSLINE 640    /* Custom Open                            */
#define WINSAVER 896     /* Window Screen Saver                     */
#define SHADOW 1024  /* Display window with shadow                  */
#define NOAUTO 2048  /* No Auto Scrolling                           */
#define NOADJ 4096   /* Do not allow the Program to adj window      */
#define STANDARD 0   /* Open Window by pulling down from top        */
#define CENTER 8192  /* Open Window from the Center out             */
#define CURTAIN 16384/* Open like a curtain                         */
#define CUSTOM 32768 /* Custom Open                                 */
#define LOGO 65536   /* Window Logo                                 */

/* #define CUTPASTE 1     Have the Ability to cut and paste         */

/*------------------------------------------------------------------*/
/*--------------------Field Option Byte Values ---------------------*/
/*------------------------------------------------------------------*/

/* check boxes, buttons */
#define FLDBORDER BIT0
#define FLDBOLD BIT1
#define FLDSINGLE BIT2

/* For right to left input */
#define FLDASSUME BIT0
#define FLDZEROS BIT1
#define FLDSPACE BIT2

/* For left to right input */
#define FLDNOPIC BIT0
#define FLDTRIM BIT1

/* For all input            */
#define FLDPASSWD BIT3
#define FLDTAB BIT4
#define FLDHIDDEN BIT5
#define FLDNOSET BIT6
#define FLDSET BIT7
#define FLDBLANK BIT8
#define FLDNORET BIT9
#define FLDSKIP BIT10
#define FLDNOALLOC BIT11
#define FLDCLEAR BIT12

/* For older code       */
#define NOCONFIRM FLDNORET
#define SETONLY FLDSET
#define SKIP FLDSKIP

/* ---------------------------------------------------------------- */
/* ------------------------For Input Control Word------------------ */
/* ---------------------------------------------------------------- */

/* First seven bits for security */

#define FLDEDIT 1  
#define FLDVIEW 2

#define ITYPE0 0
#define ITYPE1 BIT7 
#define ITYPE2 BIT8 
#define ITYPE3 BIT7+BIT8
#define ITYPE4 BIT9
#define ITYPE5 BIT9+BIT7
#define ITYPE6 BIT9+BIT8
#define ITYPE7 BIT9+BIT8+BIT7
#define ITYPE8 BIT10
#define ITYPE9 BIT10+BIT7
#define ITYPE10 BIT10+BIT8
#define ITYPE11 BIT10+BIT8+BIT7
#define ITYPE12 BIT10+BIT9
#define ITYPE13 BIT10+BIT9+BIT7
#define ITYPE14 BIT10+BIT9+BIT8
#define ITYPE15 BIT10+BIT9+BIT8+BIT7
#define FLDLTOR ITYPE0
#define FLDRTOL ITYPE1
#define FLDBUTON ITYPE2
#define FLDCHECK ITYPE3
#define FLDBLOCK ITYPE4
#define FLDCHOICE ITYPE5
#define FLDEDITOR ITYPE6

#define DTYPE0 0
#define DTYPE1 BIT11
#define DTYPE2 BIT12
#define DTYPE3 BIT11+BIT12
#define DTYPE4 BIT13
#define DTYPE5 BIT13+BIT11
#define DTYPE6 BIT13+BIT12
#define DTYPE7 BIT13+BIT12+BIT11
#define DTYPE8 BIT14
#define DTYPE9 BIT14+BIT11
#define DTYPE10 BIT14+BIT12
#define DTYPE11 BIT14+BIT12+BIT11
#define DTYPE12 BIT14+BIT13
#define DTYPE13 BIT14+BIT13+BIT11
#define DTYPE14 BIT14+BIT13+BIT12
#define DTYPE15 BIT14+BIT13+BIT12+BIT11
#define FLDSTRING DTYPE0
#define FLDINT DTYPE1
#define FLDDOUBLE DTYPE2
#define FLDJULDATE DTYPE3
#define FLDLONG DTYPE4
#define FLDFLOAT DTYPE5
#define FLDSHORT DTYPE6
#define FLDMONEY DTYPE7
#define FLDTIME DTYPE8
#define FLDDATE DTYPE9
#define FLDTIMEDATE DTYPE10
#define FLDCHAR DTYPE11
#define FLDBYTE DTYPE12

/* For older code */
#define STRING FLDSTRING
#define INTEGER FLDINT
#define REAL FLDDOUBLE
#define JULDATE FLDJULDATE
#define LONG_DATA FLDLONG
#define FLOAT_DATA FLDFLOAT
#define SHORT_DATA FLDSHORT

/* ---------------------------------------------------------------- */

/*  for say gets  */

#define GETSAY BIT10

/* ---------------------------------------------------------------- */

#define FLDLOCTEMP BIT0
#define FLDLOCREAL BIT1
#define FLDLOCUPDATE BIT2

/*------------------------------------------------------------------*/
/*--------------------Table Control Byte Values---------------------*/
/*------------------------------------------------------------------*/

#define QDISPLAY BIT0
#define MKCIRCLE BIT1
#define VALALLTBL BIT2
#define TBLRETPG BIT3
#define TBLRETUP BIT4
#define MKEXIT BIT5
#define NORETEXIT BIT6
#define RESETINS BIT7
#define STARTLEFTOFF BIT8
#define NOVALESC BIT9

/*------------------------------------------------------------------*/
/*-------------------------Logical Colors---------------------------*/
/*------------------------------------------------------------------*/
/*
#define BLACK 0  
#define BLUE  256
#define GREEN 512
#define CYAN  786
#define RED   1024
#define MAGENTA 1280
#define BROWN 1536
#define WHITE 1792
*/
#define BOLD  2048
#define BLINK 32768
#define WITH + 16*

/* ============= Color Macros ============ */
#define BLACK         0
#define BLUE          1
#define GREEN         2
#define CYAN          3
#define RED           4
#define MAGENTA       5
#define BROWN         6
#define LIGHTGRAY     7
#define DARKGRAY      8
#define LIGHTBLUE     9
#define LIGHTGREEN   10
#define LIGHTCYAN    11
#define LIGHTRED     12
#define LIGHTMAGENTA 13
#define YELLOW       14
#define WHITE        15

/*------------------------------------------------------------------*/
/*--------------------------Class Defines---------------------------*/
/*------------------------------------------------------------------*/
/*
 *         Class definition source file
 *         Make class changes to this source file
 *         Other source files will adapt
 *
 */

typedef enum window_class {
                          _NORMAL,     
                          MAIN,
                          APPLICATION,
                          TEXTBOX,    
                          LISTBOX,    
                          EDITBOX,    
                          MENUBAR,    
                          POPDOWNMENU,
                          PICTUREBOX, 
                          DIALOG,     
                          BOX,        
                          BUTTON,     
                          COMBOBOX,   
                          SAYTEXT,       
                          RADIOBUTTON,
                          CHECKBOX,   
                          SPINBUTTON, 
                          ERRORBOX,   
                          MESSAGEBOX, 
                          HELPBOX,    
                          STATUSBAR,  
                          TITLEBAR,   
                          DUMMY,
                          LOGOWIN
                          } CLASS;

/*------------------------------------------------------------------*/
/*--------------------------Menu Defines----------------------------*/
/*------------------------------------------------------------------*/

#define MENUCHECK 251

#define AUTO -1

#define UNAVAILABLE  1
#define SEPARATOR    2
#define STRPARM      4
#define MENU         8
#define RETURN      16
#define BLANKITEM   32
#define HIDE        64
#define REFRESH    256
#define CHECKED    512
#define MNUACTIVE 1024      /* DON'T use this as option. INTERNAL USE ONLY */
#define MENUERROR 2048      /* DON'T use this as option. INTERNAL USE ONLY */
#define SENDTXT   4096
#define MSCREEN   8192
#define SAA      16384

#define HORIZONTAL   1
#define VERTICAL     2
#define BORDERLESS   4
#define TITLELEFT    8
#define TITLERIGHT  16
#define TITLECENTER 32
#define AUTOMENU    64

#define PARAMARK 240

#define VC_SEG(fp) (*((unsigned *)&(fp) + 1))
#define VC_OFF(fp) (*((unsigned *)&(fp)))

#define MAXHLP 50

#define NULLCLASS     0

#define NLCHAR        '~'
#define SHORTCUT      NLCHAR 

#define FILLCHAR      176
#define STDFILLCHAR    32

#ifdef IBM
#define L_cuserid       8
#endif
