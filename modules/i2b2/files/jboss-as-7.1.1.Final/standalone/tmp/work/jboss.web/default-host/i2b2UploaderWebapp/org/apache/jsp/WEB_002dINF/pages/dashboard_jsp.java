package org.apache.jsp.WEB_002dINF.pages;

import javax.servlet.*;
import javax.servlet.http.*;
import javax.servlet.jsp.*;

public final class dashboard_jsp extends org.apache.jasper.runtime.HttpJspBase
    implements org.apache.jasper.runtime.JspSourceDependent {

  private static final JspFactory _jspxFactory = JspFactory.getDefaultFactory();

  private static java.util.List _jspx_dependants;

  private org.apache.jasper.runtime.TagHandlerPool _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody;
  private org.apache.jasper.runtime.TagHandlerPool _005fjspx_005ftagPool_005fsec_005fauthorize_0026_005faccess;
  private org.apache.jasper.runtime.TagHandlerPool _005fjspx_005ftagPool_005fc_005furl_0026_005fvar_005fvalue_005fnobody;
  private org.apache.jasper.runtime.TagHandlerPool _005fjspx_005ftagPool_005fc_005fif_0026_005ftest;

  private javax.el.ExpressionFactory _el_expressionfactory;
  private org.apache.tomcat.InstanceManager _jsp_instancemanager;

  public Object getDependants() {
    return _jspx_dependants;
  }

  public void _jspInit() {
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody = org.apache.jasper.runtime.TagHandlerPool.getTagHandlerPool(getServletConfig());
    _005fjspx_005ftagPool_005fsec_005fauthorize_0026_005faccess = org.apache.jasper.runtime.TagHandlerPool.getTagHandlerPool(getServletConfig());
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvar_005fvalue_005fnobody = org.apache.jasper.runtime.TagHandlerPool.getTagHandlerPool(getServletConfig());
    _005fjspx_005ftagPool_005fc_005fif_0026_005ftest = org.apache.jasper.runtime.TagHandlerPool.getTagHandlerPool(getServletConfig());
    _el_expressionfactory = _jspxFactory.getJspApplicationContext(getServletConfig().getServletContext()).getExpressionFactory();
    _jsp_instancemanager = org.apache.jasper.runtime.InstanceManagerFactory.getInstanceManager(getServletConfig());
  }

  public void _jspDestroy() {
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.release();
    _005fjspx_005ftagPool_005fsec_005fauthorize_0026_005faccess.release();
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvar_005fvalue_005fnobody.release();
    _005fjspx_005ftagPool_005fc_005fif_0026_005ftest.release();
  }

  public void _jspService(HttpServletRequest request, HttpServletResponse response)
        throws java.io.IOException, ServletException {

    PageContext pageContext = null;
    HttpSession session = null;
    ServletContext application = null;
    ServletConfig config = null;
    JspWriter out = null;
    Object page = this;
    JspWriter _jspx_out = null;
    PageContext _jspx_page_context = null;


    try {
      response.setContentType("text/html");
      response.addHeader("X-Powered-By", "JSP/2.2");
      pageContext = _jspxFactory.getPageContext(this, request, response,
      			null, true, 8192, true);
      _jspx_page_context = pageContext;
      application = pageContext.getServletContext();
      config = pageContext.getServletConfig();
      session = pageContext.getSession();
      out = pageContext.getOut();
      _jspx_out = out;

      out.write("\r\n");
      out.write("\r\n");
      out.write("<!DOCTYPE html>\r\n");
      out.write("<html lang=\"en\">\r\n");
      out.write("<head>\r\n");
      out.write("    <meta charset=\"utf-8\">\r\n");
      out.write("    <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">\r\n");
      out.write("    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\r\n");
      out.write("    <meta name=\"description\" content=\"\">\r\n");
      out.write("    <meta name=\"author\" content=\"\">\r\n");
      out.write("    <link rel=\"icon\" href=\"../favicon.ico\">\r\n");
      out.write("\r\n");
      out.write("    <title>Brisskit Portal</title>\r\n");
      out.write("\r\n");
      out.write("    <!-- Bootstrap core CSS -->\r\n");
      out.write("    <link href=\"");
      if (_jspx_meth_c_005furl_005f0(_jspx_page_context))
        return;
      out.write("\" rel=\"stylesheet\">\r\n");
      out.write("\r\n");
      out.write("    <!-- Custom styles for this template -->\r\n");
      out.write("    <link href=\"");
      if (_jspx_meth_c_005furl_005f1(_jspx_page_context))
        return;
      out.write("\" rel=\"stylesheet\">\r\n");
      out.write("    \r\n");
      out.write("     <!-- Generic page styles -->\r\n");
      out.write("<!--    <link rel=\"stylesheet\" href=\"../dist/css/style.css\"> -->\r\n");
      out.write("    <!-- CSS to style the file input field as button and adjust the Bootstrap progress bars -->\r\n");
      out.write("<!--    <link rel=\"stylesheet\" href=\"../dist/css/jquery.fileupload.css\"> -->\r\n");
      out.write("\r\n");
      out.write("    <!-- Just for debugging purposes. Don't actually copy these 2 lines! -->\r\n");
      out.write("    <!--[if lt IE 9]><script src=\"../assets/js/ie8-responsive-file-warning.js\"></script><![endif]-->\r\n");
      out.write("    <!--<script src=\"../assets/js/ie-emulation-modes-warning.js\"></script>-->\r\n");
      out.write("\r\n");
      out.write("    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->\r\n");
      out.write("    <!--[if lt IE 9]>\r\n");
      out.write("      <script src=\"https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js\"></script>\r\n");
      out.write("      <script src=\"https://oss.maxcdn.com/respond/1.4.2/respond.min.js\"></script>\r\n");
      out.write("    <![endif]-->\r\n");
      out.write("  </head>\r\n");
      out.write("<body>\r\n");
      out.write("\t<!-- <h1>Title : ");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${title}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("</h1>\r\n");
      out.write("\t<h1>Message : ");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${message}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("</h1>\r\n");
      out.write("    -->\r\n");
      out.write("\t");
      if (_jspx_meth_sec_005fauthorize_005f0(_jspx_page_context))
        return;
      out.write("\r\n");
      out.write("</body>\r\n");
      out.write("</html>");
    } catch (Throwable t) {
      if (!(t instanceof SkipPageException)){
        out = _jspx_out;
        if (out != null && out.getBufferSize() != 0)
          try { out.clearBuffer(); } catch (java.io.IOException e) {}
        if (_jspx_page_context != null) _jspx_page_context.handlePageException(t);
      }
    } finally {
      _jspxFactory.releasePageContext(_jspx_page_context);
    }
  }

  private boolean _jspx_meth_c_005furl_005f0(PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f0 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f0.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f0.setParent(null);
    // /WEB-INF/pages/dashboard.jsp(17,16) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f0.setValue("/resources/dist/css/bootstrap.min.css");
    int _jspx_eval_c_005furl_005f0 = _jspx_th_c_005furl_005f0.doStartTag();
    if (_jspx_th_c_005furl_005f0.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f0);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f0);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f1(PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f1 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f1.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f1.setParent(null);
    // /WEB-INF/pages/dashboard.jsp(20,16) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f1.setValue("/resources/dist/css/dashboard.css");
    int _jspx_eval_c_005furl_005f1 = _jspx_th_c_005furl_005f1.doStartTag();
    if (_jspx_th_c_005furl_005f1.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f1);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f1);
    return false;
  }

  private boolean _jspx_meth_sec_005fauthorize_005f0(PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  sec:authorize
    org.springframework.security.taglibs.authz.JspAuthorizeTag _jspx_th_sec_005fauthorize_005f0 = (org.springframework.security.taglibs.authz.JspAuthorizeTag) _005fjspx_005ftagPool_005fsec_005fauthorize_0026_005faccess.get(org.springframework.security.taglibs.authz.JspAuthorizeTag.class);
    _jspx_th_sec_005fauthorize_005f0.setPageContext(_jspx_page_context);
    _jspx_th_sec_005fauthorize_005f0.setParent(null);
    // /WEB-INF/pages/dashboard.jsp(41,1) name = access type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_sec_005fauthorize_005f0.setAccess("hasRole('ROLE_USER')");
    int _jspx_eval_sec_005fauthorize_005f0 = _jspx_th_sec_005fauthorize_005f0.doStartTag();
    if (_jspx_eval_sec_005fauthorize_005f0 != javax.servlet.jsp.tagext.Tag.SKIP_BODY) {
      out.write("\r\n");
      out.write("\t\t<!-- For login user -->\r\n");
      out.write("\t\t");
      if (_jspx_meth_c_005furl_005f2(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t<nav class=\"navbar navbar-inverse navbar-fixed-top\" role=\"navigation\">\r\n");
      out.write("      <div class=\"container-fluid\">\r\n");
      out.write("        <div class=\"navbar-header\">         \r\n");
      out.write("          <a class=\"navbar-brand\" href=\"#\">Brisskit Portal</a>          \r\n");
      out.write("        </div>\r\n");
      out.write("        <div class=\"navbar-header\">\r\n");
      out.write("        <img src=\"");
      if (_jspx_meth_c_005furl_005f3(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\" alt=\"Jisc Logo\" style=\"border:5px solid black\">\r\n");
      out.write("          &nbsp;\r\n");
      out.write("          <img src=\"");
      if (_jspx_meth_c_005furl_005f4(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\" alt=\"Brisskit Logo\" style=\"border:5px solid black\">\r\n");
      out.write("        </div>  \r\n");
      out.write("        <div id=\"navbar\" class=\"navbar-collapse collapse\">\r\n");
      out.write("          <ul class=\"nav navbar-nav navbar-right\">\r\n");
      out.write("            <li><a href=\"welcome\">Dashboard</a></li>\r\n");
      out.write("            <!-- <li><a href=\"createnewuser\">Create New User</a></li>\r\n");
      out.write("            <li><a href=\"changepassword\">Change Password</a></li>  -->\r\n");
      out.write("            <li><a href=\"settings\">Settings</a></li>\r\n");
      out.write("            <script>\r\n");
      out.write("\t\t\tfunction formSubmit() {\r\n");
      out.write("\t\t\t\tdocument.getElementById(\"logoutForm\").submit();\r\n");
      out.write("\t\t\t}\r\n");
      out.write("\t\t    </script>\r\n");
      out.write("\t\t    ");
      if (_jspx_meth_c_005fif_005f0(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\r\n");
      out.write("            \r\n");
      out.write("            \r\n");
      out.write("            <li><a href=\"http://wiki.brisskit.le.ac.uk/wiki/I2b2_Uploader_Portal_Guide\" target=\"_blank\">Help</a></li>\r\n");
      out.write("          </ul>\r\n");
      out.write("        </div>\r\n");
      out.write("      </div>\r\n");
      out.write("    </nav>\r\n");
      out.write("\r\n");
      out.write("    <div class=\"container-fluid\">\r\n");
      out.write("      <div class=\"row\">\r\n");
      out.write("        <div class=\"col-sm-3 col-md-2 sidebar\">\r\n");
      out.write("          <ul class=\"nav nav-sidebar\">\r\n");
      out.write("            <li><a href=\"np_uploadxls\">Upload XLS - New Project</a></li>\r\n");
      out.write("            <li><a href=\"ex_uploadxls\">Upload XLS - Existing Project</a></li>\r\n");
      out.write("            <!-- <li><a href=\"uploadxls\">Upload XLS</a></li>\r\n");
      out.write("            <li><a href=\"uploadodf\">Upload ODF</a></li>\r\n");
      out.write("            <li><a href=\"#\">Upload Ontology XML</a></li>  -->\r\n");
      out.write("            <!-- <li><a href=\"createproject\">Create Project</a></li>  -->\r\n");
      out.write("            <li><a href=\"ontmapper\">Ontology Mapper</a></li>\r\n");
      out.write("            <li><a href=\"deleteproject\">Delete Project</a></li>\r\n");
      out.write("            <li><a href=\"exportdata\">View Data</a></li>\r\n");
      out.write("            <li><a href=\"i2b2instance\">Your i2b2 instance</a></li>\r\n");
      out.write("            <!-- <li><a href=\"#\">Your redcap instance</a></li>\r\n");
      out.write("            <li><a href=\"#\">Your open specimen instance</a></li>\r\n");
      out.write("            <li><a href=\"#\">Amazon / Azure account</a></li>  -->\r\n");
      out.write("          </ul>\r\n");
      out.write("          <!-- \r\n");
      out.write("          <ul class=\"nav nav-sidebar\">\r\n");
      out.write("            <li><a href=\"\">Nav item</a></li>\r\n");
      out.write("            <li><a href=\"\">Nav item again</a></li>\r\n");
      out.write("            <li><a href=\"\">One more nav</a></li>\r\n");
      out.write("            <li><a href=\"\">Another nav item</a></li>\r\n");
      out.write("            <li><a href=\"\">More navigation</a></li>\r\n");
      out.write("          </ul>\r\n");
      out.write("          <ul class=\"nav nav-sidebar\">\r\n");
      out.write("            <li><a href=\"\">Nav item again</a></li>\r\n");
      out.write("            <li><a href=\"\">One more nav</a></li>\r\n");
      out.write("            <li><a href=\"\">Another nav item</a></li>\r\n");
      out.write("          </ul>\r\n");
      out.write("           -->\r\n");
      out.write("        </div>\r\n");
      out.write("        \r\n");
      out.write("        \r\n");
      out.write("        <div class=\"col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main\">\r\n");
      out.write("          <h1 class=\"page-header\">Dashboard</h1>\r\n");
      out.write("\t\t  Welcome to the dashboard, please select item from menu on the left\r\n");
      out.write("\t\t  <br>\r\n");
      out.write("\t\t  <br>\r\n");
      out.write("\t\t  \r\n");
      out.write("<!-- \r\n");
      out.write("         <li><a href=\"");
      if (_jspx_meth_c_005furl_005f5(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">EG1-laheart.xlsx</a></li>\r\n");
      out.write("         <li><a href=\"");
      if (_jspx_meth_c_005furl_005f6(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">pharma_large.xls</a></li>\r\n");
      out.write("         <li><a href=\"");
      if (_jspx_meth_c_005furl_005f7(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project1_basic.xls</a></li>\r\n");
      out.write("         <li><a href=\"");
      if (_jspx_meth_c_005furl_005f8(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project2_1_optional_fields.xls</a></li>\r\n");
      out.write("         <li><a href=\"");
      if (_jspx_meth_c_005furl_005f9(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project2_2_new_col_new_patient.xls</a></li>\r\n");
      out.write("         <li><a href=\"");
      if (_jspx_meth_c_005furl_005f10(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project2_3_new_col_same_patients.xls</a></li>\r\n");
      out.write(" -->\r\n");
      out.write(" \r\n");
      out.write("The i2b2 uploader aims to upload a series of observable facts from a spreadsheet to i2b2.\r\n");
      out.write("<br><br> \r\n");
      out.write("A comprehensive guide is available if you click on 'help' in the right hand corner above.\r\n");
      out.write("<br><br> \r\n");
      out.write("Please follow these simple rules to upload :\r\n");
      out.write("<br><br>\r\n");
      out.write("<li>Spreadsheets must be in the format .xls or .xlsx</li>\r\n");
      out.write("<li>It is mandatory that the first column in the spreadsheet is 'ID' and the two fields below it are set to null. The ID column should contain patient identifiers.</li>\r\n");
      out.write("<li>The 1st row of the spreadsheet contains column headings which represent short codes of the facts being collected for the patients. </li>\r\n");
      out.write("<li>The 2nd row of the spreadsheet contains tool tips which are shown in the i2b2 client, within the ontology.</li>\r\n");
      out.write("<li>The 3rd row of the spreadsheet contains i2b2 ontology codes. It also contain units in square brackets [] which represent the unit of measure. \r\n");
      out.write("    If the unit is known, then for example you can use [kgs], [cms] etc. For text you have to use [text], if the square brackets are omited, the fact is treated as an enumeration.</li>\r\n");
      out.write("\r\n");
      out.write("<br>\r\n");
      out.write("Here are some example spreadsheets for you to try :\r\n");
      out.write("<br>\r\n");
      out.write("<br>\r\n");
      out.write("\t\t  \t\t  \r\n");
      out.write("<table class=\"table table-striped\">\r\n");
      out.write("  <tr>\r\n");
      out.write("    <td>File</td>\r\n");
      out.write("    <td>Project</td>\r\n");
      out.write("    <td>Description</td>\r\n");
      out.write("  </tr>\r\n");
      out.write("  <tr>\r\n");
      out.write("    <td><a href=\"");
      if (_jspx_meth_c_005furl_005f11(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project1_basic.xls</a></td>\r\n");
      out.write("    <td>project1</td>\r\n");
      out.write("    <td>This is the most simplest spreadsheet. It only has a single tab 'Data' and it uploads data for 5 patients. \r\n");
      out.write("        It has a mixture of numerical, textual, date and enumerated values. Note that dates have to be in the format YYYY-MM-DD. \r\n");
      out.write("        To better understand the upload to i2b2, upload this spreadsheet into i2b2 and examine the spreadsheets first 3 rows whilst using i2b2.</td>\r\n");
      out.write("  </tr>\r\n");
      out.write("  <tr>\r\n");
      out.write("    <td><a href=\"");
      if (_jspx_meth_c_005furl_005f12(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project2_1_optional_fields.xls</a></td>\r\n");
      out.write("    <td>project2</td>\r\n");
      out.write("    <td>This spreadsheet contains 5 patients and has 2 aditional optional tabs 'Lookup' and 'Breakdowns'. The 'Lookup' tab allows \r\n");
      out.write("    the mapping of codes in patient rows to meaningful names, suppose you have numerical codes 1,2,3 signifying heart, lung, brain. \r\n");
      out.write("    These can be mapped in the 'Lookup' tab. The 'Breakdowns' tab maps available breaks downs in i2b2 which are Age, Gender, Race and Vital Status\r\n");
      out.write("    to columns in the 'Data' tab. For example in this spreadsheet in the 'breakdowns' tab Vital Status is mapped to the column Death in the 'Data' tab.  \r\n");
      out.write("    This spreadsheet also contains 5 optional columns which are OBS_START_DATE,\tAGE, GENDER, RACE and DEATH. OBS_START_DATE is a special column which \r\n");
      out.write("    marks the start date of all facts in the particular row. AGE, GENDER, RACE and DEATH are needed for i2b2 breakdowns.\r\n");
      out.write("    </td>\r\n");
      out.write("  </tr>\r\n");
      out.write("  <tr>\r\n");
      out.write("    <td><a href=\"");
      if (_jspx_meth_c_005furl_005f13(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project2_2_new_col_new_patient.xls</a></td>\r\n");
      out.write("    <td>project2</td>\r\n");
      out.write("    <td>This spreadsheet should be added to the previously created project. It contains 5 new patients and a new txt fact column.</td>\r\n");
      out.write("  </tr>\r\n");
      out.write("  <tr>\r\n");
      out.write("    <td><a href=\"");
      if (_jspx_meth_c_005furl_005f14(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">project2_3_new_col_same_patients.xls</a></td>\r\n");
      out.write("    <td>project2</td>\r\n");
      out.write("    <td>This spreadsheet should be added to the previously created project. It contains existing 5 new patients and a new text fact column.</td>\r\n");
      out.write("  </tr>\r\n");
      out.write("  <tr>\r\n");
      out.write("    <td><a href=\"");
      if (_jspx_meth_c_005furl_005f15(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">EG1-laheart.xlsx</a></td>\r\n");
      out.write("    <td>laheart</td>\r\n");
      out.write("    <td>This spreadsheet contains 200 patients and has 2 aditional optional tabs 'Lookup' and 'Breakdowns'. It also maps 2 breakdowns to the optional columns AGE_1950\r\n");
      out.write("        and DEATH.</td>\r\n");
      out.write("  </tr>\r\n");
      out.write("  <tr>\r\n");
      out.write("    <td><a href=\"");
      if (_jspx_meth_c_005furl_005f16(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\">pharma_large.xls</a></td>\r\n");
      out.write("    <td>pharma</td>\r\n");
      out.write("    <td>This spreadsheet contains 4997 patients and it places default values in the optional columns AGE, GENDER, RACE and DEATH.</td>\r\n");
      out.write("  </tr>\r\n");
      out.write("</table>\r\n");
      out.write("\r\n");
      out.write("<!-- \r\n");
      out.write("          <div class=\"row placeholders\">\r\n");
      out.write("            <div class=\"col-xs-6 col-sm-3 placeholder\">\r\n");
      out.write("              <img data-src=\"holder.js/200x200/auto/sky\" class=\"img-responsive\" alt=\"Generic placeholder thumbnail\">\r\n");
      out.write("              <h4>Label</h4>\r\n");
      out.write("              <span class=\"text-muted\">Something else</span>\r\n");
      out.write("            </div>\r\n");
      out.write("            <div class=\"col-xs-6 col-sm-3 placeholder\">\r\n");
      out.write("              <img data-src=\"holder.js/200x200/auto/vine\" class=\"img-responsive\" alt=\"Generic placeholder thumbnail\">\r\n");
      out.write("              <h4>Label</h4>\r\n");
      out.write("              <span class=\"text-muted\">Something else</span>\r\n");
      out.write("            </div>\r\n");
      out.write("            <div class=\"col-xs-6 col-sm-3 placeholder\">\r\n");
      out.write("              <img data-src=\"holder.js/200x200/auto/sky\" class=\"img-responsive\" alt=\"Generic placeholder thumbnail\">\r\n");
      out.write("              <h4>Label</h4>\r\n");
      out.write("              <span class=\"text-muted\">Something else</span>\r\n");
      out.write("            </div>\r\n");
      out.write("            <div class=\"col-xs-6 col-sm-3 placeholder\">\r\n");
      out.write("              <img data-src=\"holder.js/200x200/auto/vine\" class=\"img-responsive\" alt=\"Generic placeholder thumbnail\">\r\n");
      out.write("              <h4>Label</h4>\r\n");
      out.write("              <span class=\"text-muted\">Something else</span>\r\n");
      out.write("            </div>\r\n");
      out.write("          </div>\r\n");
      out.write(" -->\r\n");
      out.write("\r\n");
      out.write("<!--           <h2 class=\"sub-header\">Section title</h2>  -->\r\n");
      out.write("\r\n");
      out.write("<!--\r\n");
      out.write("          <div class=\"table-responsive\">\r\n");
      out.write("            <table class=\"table table-striped\">\r\n");
      out.write("              <thead>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <th>#</th>\r\n");
      out.write("                  <th>Header</th>\r\n");
      out.write("                  <th>Header</th>\r\n");
      out.write("                  <th>Header</th>\r\n");
      out.write("                  <th>Header</th>\r\n");
      out.write("                </tr>\r\n");
      out.write("              </thead>\r\n");
      out.write("              <tbody>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,001</td>\r\n");
      out.write("                  <td>Lorem</td>\r\n");
      out.write("                  <td>ipsum</td>\r\n");
      out.write("                  <td>dolor</td>\r\n");
      out.write("                  <td>sit</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,002</td>\r\n");
      out.write("                  <td>amet</td>\r\n");
      out.write("                  <td>consectetur</td>\r\n");
      out.write("                  <td>adipiscing</td>\r\n");
      out.write("                  <td>elit</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,003</td>\r\n");
      out.write("                  <td>Integer</td>\r\n");
      out.write("                  <td>nec</td>\r\n");
      out.write("                  <td>odio</td>\r\n");
      out.write("                  <td>Praesent</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,003</td>\r\n");
      out.write("                  <td>libero</td>\r\n");
      out.write("                  <td>Sed</td>\r\n");
      out.write("                  <td>cursus</td>\r\n");
      out.write("                  <td>ante</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,004</td>\r\n");
      out.write("                  <td>dapibus</td>\r\n");
      out.write("                  <td>diam</td>\r\n");
      out.write("                  <td>Sed</td>\r\n");
      out.write("                  <td>nisi</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,005</td>\r\n");
      out.write("                  <td>Nulla</td>\r\n");
      out.write("                  <td>quis</td>\r\n");
      out.write("                  <td>sem</td>\r\n");
      out.write("                  <td>at</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,006</td>\r\n");
      out.write("                  <td>nibh</td>\r\n");
      out.write("                  <td>elementum</td>\r\n");
      out.write("                  <td>imperdiet</td>\r\n");
      out.write("                  <td>Duis</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,007</td>\r\n");
      out.write("                  <td>sagittis</td>\r\n");
      out.write("                  <td>ipsum</td>\r\n");
      out.write("                  <td>Praesent</td>\r\n");
      out.write("                  <td>mauris</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,008</td>\r\n");
      out.write("                  <td>Fusce</td>\r\n");
      out.write("                  <td>nec</td>\r\n");
      out.write("                  <td>tellus</td>\r\n");
      out.write("                  <td>sed</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,009</td>\r\n");
      out.write("                  <td>augue</td>\r\n");
      out.write("                  <td>semper</td>\r\n");
      out.write("                  <td>porta</td>\r\n");
      out.write("                  <td>Mauris</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,010</td>\r\n");
      out.write("                  <td>massa</td>\r\n");
      out.write("                  <td>Vestibulum</td>\r\n");
      out.write("                  <td>lacinia</td>\r\n");
      out.write("                  <td>arcu</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,011</td>\r\n");
      out.write("                  <td>eget</td>\r\n");
      out.write("                  <td>nulla</td>\r\n");
      out.write("                  <td>Class</td>\r\n");
      out.write("                  <td>aptent</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,012</td>\r\n");
      out.write("                  <td>taciti</td>\r\n");
      out.write("                  <td>sociosqu</td>\r\n");
      out.write("                  <td>ad</td>\r\n");
      out.write("                  <td>litora</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,013</td>\r\n");
      out.write("                  <td>torquent</td>\r\n");
      out.write("                  <td>per</td>\r\n");
      out.write("                  <td>conubia</td>\r\n");
      out.write("                  <td>nostra</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,014</td>\r\n");
      out.write("                  <td>per</td>\r\n");
      out.write("                  <td>inceptos</td>\r\n");
      out.write("                  <td>himenaeos</td>\r\n");
      out.write("                  <td>Curabitur</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("                <tr>\r\n");
      out.write("                  <td>1,015</td>\r\n");
      out.write("                  <td>sodales</td>\r\n");
      out.write("                  <td>ligula</td>\r\n");
      out.write("                  <td>in</td>\r\n");
      out.write("                  <td>libero</td>\r\n");
      out.write("                </tr>\r\n");
      out.write("              </tbody>\r\n");
      out.write("            </table>\r\n");
      out.write("          </div>\r\n");
      out.write("        -->\r\n");
      out.write("        \r\n");
      out.write("        </div>\r\n");
      out.write("      </div>\r\n");
      out.write("    </div>\r\n");
      out.write("\r\n");
      out.write("    <!-- Bootstrap core JavaScript\r\n");
      out.write("    ================================================== -->\r\n");
      out.write("    <!-- Placed at the end of the document so the pages load faster -->\r\n");
      out.write("    <script src=\"https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js\"></script>\r\n");
      out.write("    <script src=\"");
      if (_jspx_meth_c_005furl_005f17(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\"></script>\r\n");
      out.write("    <!-- <script src=\"");
      if (_jspx_meth_c_005furl_005f18(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\"></script>-->\r\n");
      out.write("    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->\r\n");
      out.write("    <script src=\"");
      if (_jspx_meth_c_005furl_005f19(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\"></script>\r\n");
      out.write("    \r\n");
      out.write("<script src=\"");
      if (_jspx_meth_c_005furl_005f20(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\"></script>\r\n");
      out.write("<!-- The Iframe Transport is required for browsers without support for XHR file uploads -->\r\n");
      out.write("<script src=\"");
      if (_jspx_meth_c_005furl_005f21(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\"></script>\r\n");
      out.write("<!-- The basic File Upload plugin -->\r\n");
      out.write("<script src=\"");
      if (_jspx_meth_c_005furl_005f22(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\"></script>\r\n");
      out.write("<script>\r\n");
      out.write("/*jslint unparam: true */\r\n");
      out.write("/*global window, $ */\r\n");
      out.write("$(function () {\r\n");
      out.write("    'use strict';\r\n");
      out.write("    // Change this to the location of your server-side upload handler:\r\n");
      out.write("    var url = window.location.hostname === 'blueimp.github.io' ?\r\n");
      out.write("                '//jquery-file-upload.appspot.com/' : 'server/php/';\r\n");
      out.write("    $('#fileupload').fileupload({\r\n");
      out.write("        url: url,\r\n");
      out.write("        dataType: 'json',\r\n");
      out.write("        done: function (e, data) {\r\n");
      out.write("            $.each(data.result.files, function (index, file) {\r\n");
      out.write("                $('<p/>').text(file.name).appendTo('#files');\r\n");
      out.write("            });\r\n");
      out.write("        },\r\n");
      out.write("        progressall: function (e, data) {\r\n");
      out.write("            var progress = parseInt(data.loaded / data.total * 100, 10);\r\n");
      out.write("            $('#progress .progress-bar').css(\r\n");
      out.write("                'width',\r\n");
      out.write("                progress + '%'\r\n");
      out.write("            );\r\n");
      out.write("        }\r\n");
      out.write("    }).prop('disabled', !$.support.fileInput)\r\n");
      out.write("        .parent().addClass($.support.fileInput ? undefined : 'disabled');\r\n");
      out.write("});\r\n");
      out.write("</script>\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t<form action=\"");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${logoutUrl}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("\" method=\"post\" id=\"logoutForm\">\r\n");
      out.write("\t\t\t<input type=\"hidden\" name=\"");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${_csrf.parameterName}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("\"\r\n");
      out.write("\t\t\t\tvalue=\"");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${_csrf.token}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("\" />\r\n");
      out.write("\t\t    </form>\r\n");
      out.write("\t\t    \r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t\r\n");
      out.write("\t\t <!-- \r\n");
      out.write("\t\t<form action=\"");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${logoutUrl}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("\" method=\"post\" id=\"logoutForm\">\r\n");
      out.write("\t\t\t<input type=\"hidden\" name=\"");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${_csrf.parameterName}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("\"\r\n");
      out.write("\t\t\t\tvalue=\"");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${_csrf.token}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("\" />\r\n");
      out.write("\t\t</form>\r\n");
      out.write("\t\t \r\n");
      out.write("\t\t\r\n");
      out.write("\t\t<script>\r\n");
      out.write("\t\t\tfunction formSubmit() {\r\n");
      out.write("\t\t\t\tdocument.getElementById(\"logoutForm\").submit();\r\n");
      out.write("\t\t\t}\r\n");
      out.write("\t\t</script>\r\n");
      out.write("\t\tHello jsp ");
      out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${logoutUrl}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
      out.write("\r\n");
      out.write("\t\t");
      if (_jspx_meth_c_005fif_005f1(_jspx_th_sec_005fauthorize_005f0, _jspx_page_context))
        return true;
      out.write("\r\n");
      out.write(" -->\r\n");
      out.write("\r\n");
      out.write("\t");
    }
    if (_jspx_th_sec_005fauthorize_005f0.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fsec_005fauthorize_0026_005faccess.reuse(_jspx_th_sec_005fauthorize_005f0);
      return true;
    }
    _005fjspx_005ftagPool_005fsec_005fauthorize_0026_005faccess.reuse(_jspx_th_sec_005fauthorize_005f0);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f2(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f2 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvar_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f2.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f2.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(43,2) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f2.setValue("/j_spring_security_logout");
    // /WEB-INF/pages/dashboard.jsp(43,2) name = var type = java.lang.String reqTime = false required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f2.setVar("logoutUrl");
    int _jspx_eval_c_005furl_005f2 = _jspx_th_c_005furl_005f2.doStartTag();
    if (_jspx_th_c_005furl_005f2.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvar_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f2);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvar_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f2);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f3(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f3 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f3.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f3.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(56,18) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f3.setValue("/resources/images/logo.png");
    int _jspx_eval_c_005furl_005f3 = _jspx_th_c_005furl_005f3.doStartTag();
    if (_jspx_th_c_005furl_005f3.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f3);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f3);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f4(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f4 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f4.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f4.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(58,20) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f4.setValue("/resources/images/brisskit-logo-small.png");
    int _jspx_eval_c_005furl_005f4 = _jspx_th_c_005furl_005f4.doStartTag();
    if (_jspx_th_c_005furl_005f4.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f4);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f4);
    return false;
  }

  private boolean _jspx_meth_c_005fif_005f0(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:if
    org.apache.taglibs.standard.tag.rt.core.IfTag _jspx_th_c_005fif_005f0 = (org.apache.taglibs.standard.tag.rt.core.IfTag) _005fjspx_005ftagPool_005fc_005fif_0026_005ftest.get(org.apache.taglibs.standard.tag.rt.core.IfTag.class);
    _jspx_th_c_005fif_005f0.setPageContext(_jspx_page_context);
    _jspx_th_c_005fif_005f0.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(71,6) name = test type = boolean reqTime = true required = true fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005fif_005f0.setTest(((java.lang.Boolean) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${pageContext.request.userPrincipal.name != null}", java.lang.Boolean.class, (PageContext)_jspx_page_context, null, false)).booleanValue());
    int _jspx_eval_c_005fif_005f0 = _jspx_th_c_005fif_005f0.doStartTag();
    if (_jspx_eval_c_005fif_005f0 != javax.servlet.jsp.tagext.Tag.SKIP_BODY) {
      do {
        out.write("\r\n");
        out.write("\t\t\t<li><a href=\"javascript:formSubmit()\">Logout</a></li>\r\n");
        out.write("\t\t    ");
        int evalDoAfterBody = _jspx_th_c_005fif_005f0.doAfterBody();
        if (evalDoAfterBody != javax.servlet.jsp.tagext.BodyTag.EVAL_BODY_AGAIN)
          break;
      } while (true);
    }
    if (_jspx_th_c_005fif_005f0.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005fif_0026_005ftest.reuse(_jspx_th_c_005fif_005f0);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005fif_0026_005ftest.reuse(_jspx_th_c_005fif_005f0);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f5(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f5 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f5.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f5.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(124,22) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f5.setValue("/resources/spreadsheets/EG1-laheart.xlsx");
    int _jspx_eval_c_005furl_005f5 = _jspx_th_c_005furl_005f5.doStartTag();
    if (_jspx_th_c_005furl_005f5.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f5);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f5);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f6(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f6 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f6.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f6.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(125,22) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f6.setValue("/resources/spreadsheets/pharma_large.xls");
    int _jspx_eval_c_005furl_005f6 = _jspx_th_c_005furl_005f6.doStartTag();
    if (_jspx_th_c_005furl_005f6.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f6);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f6);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f7(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f7 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f7.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f7.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(126,22) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f7.setValue("/resources/spreadsheets/project1_basic.xls");
    int _jspx_eval_c_005furl_005f7 = _jspx_th_c_005furl_005f7.doStartTag();
    if (_jspx_th_c_005furl_005f7.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f7);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f7);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f8(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f8 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f8.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f8.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(127,22) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f8.setValue("/resources/spreadsheets/project2_1_optional_fields.xls");
    int _jspx_eval_c_005furl_005f8 = _jspx_th_c_005furl_005f8.doStartTag();
    if (_jspx_th_c_005furl_005f8.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f8);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f8);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f9(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f9 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f9.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f9.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(128,22) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f9.setValue("/resources/spreadsheets/project2_2_new_col_new_patient.xls");
    int _jspx_eval_c_005furl_005f9 = _jspx_th_c_005furl_005f9.doStartTag();
    if (_jspx_th_c_005furl_005f9.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f9);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f9);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f10(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f10 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f10.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f10.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(129,22) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f10.setValue("/resources/spreadsheets/project2_3_new_col_same_patients.xls");
    int _jspx_eval_c_005furl_005f10 = _jspx_th_c_005furl_005f10.doStartTag();
    if (_jspx_th_c_005furl_005f10.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f10);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f10);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f11(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f11 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f11.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f11.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(157,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f11.setValue("/resources/spreadsheets/project1_basic.xls");
    int _jspx_eval_c_005furl_005f11 = _jspx_th_c_005furl_005f11.doStartTag();
    if (_jspx_th_c_005furl_005f11.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f11);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f11);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f12(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f12 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f12.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f12.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(164,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f12.setValue("/resources/spreadsheets/project2_1_optional_fields.xls");
    int _jspx_eval_c_005furl_005f12 = _jspx_th_c_005furl_005f12.doStartTag();
    if (_jspx_th_c_005furl_005f12.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f12);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f12);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f13(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f13 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f13.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f13.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(175,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f13.setValue("/resources/spreadsheets/project2_2_new_col_new_patient.xls");
    int _jspx_eval_c_005furl_005f13 = _jspx_th_c_005furl_005f13.doStartTag();
    if (_jspx_th_c_005furl_005f13.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f13);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f13);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f14(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f14 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f14.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f14.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(180,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f14.setValue("/resources/spreadsheets/project2_3_new_col_same_patients.xls");
    int _jspx_eval_c_005furl_005f14 = _jspx_th_c_005furl_005f14.doStartTag();
    if (_jspx_th_c_005furl_005f14.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f14);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f14);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f15(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f15 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f15.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f15.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(185,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f15.setValue("/resources/spreadsheets/EG1-laheart.xlsx");
    int _jspx_eval_c_005furl_005f15 = _jspx_th_c_005furl_005f15.doStartTag();
    if (_jspx_th_c_005furl_005f15.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f15);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f15);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f16(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f16 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f16.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f16.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(191,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f16.setValue("/resources/spreadsheets/pharma_large.xls");
    int _jspx_eval_c_005furl_005f16 = _jspx_th_c_005furl_005f16.doStartTag();
    if (_jspx_th_c_005furl_005f16.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f16);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f16);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f17(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f17 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f17.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f17.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(362,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f17.setValue("/resources/dist/js/bootstrap.min.js");
    int _jspx_eval_c_005furl_005f17 = _jspx_th_c_005furl_005f17.doStartTag();
    if (_jspx_th_c_005furl_005f17.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f17);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f17);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f18(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f18 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f18.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f18.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(363,22) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f18.setValue("/resources/assets/js/docs.min.js");
    int _jspx_eval_c_005furl_005f18 = _jspx_th_c_005furl_005f18.doStartTag();
    if (_jspx_th_c_005furl_005f18.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f18);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f18);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f19(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f19 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f19.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f19.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(365,17) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f19.setValue("/resources/assets/js/ie10-viewport-bug-workaround.js");
    int _jspx_eval_c_005furl_005f19 = _jspx_th_c_005furl_005f19.doStartTag();
    if (_jspx_th_c_005furl_005f19.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f19);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f19);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f20(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f20 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f20.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f20.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(367,13) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f20.setValue("/resources/dist/js/vendor/jquery.ui.widget.js");
    int _jspx_eval_c_005furl_005f20 = _jspx_th_c_005furl_005f20.doStartTag();
    if (_jspx_th_c_005furl_005f20.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f20);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f20);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f21(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f21 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f21.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f21.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(369,13) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f21.setValue("/resources/dist/js/jquery.iframe-transport.js");
    int _jspx_eval_c_005furl_005f21 = _jspx_th_c_005furl_005f21.doStartTag();
    if (_jspx_th_c_005furl_005f21.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f21);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f21);
    return false;
  }

  private boolean _jspx_meth_c_005furl_005f22(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:url
    org.apache.taglibs.standard.tag.rt.core.UrlTag _jspx_th_c_005furl_005f22 = (org.apache.taglibs.standard.tag.rt.core.UrlTag) _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.get(org.apache.taglibs.standard.tag.rt.core.UrlTag.class);
    _jspx_th_c_005furl_005f22.setPageContext(_jspx_page_context);
    _jspx_th_c_005furl_005f22.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(371,13) name = value type = null reqTime = true required = false fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005furl_005f22.setValue("/resources/dist/js/jquery.fileupload.js");
    int _jspx_eval_c_005furl_005f22 = _jspx_th_c_005furl_005f22.doStartTag();
    if (_jspx_th_c_005furl_005f22.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f22);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005furl_0026_005fvalue_005fnobody.reuse(_jspx_th_c_005furl_005f22);
    return false;
  }

  private boolean _jspx_meth_c_005fif_005f1(javax.servlet.jsp.tagext.JspTag _jspx_th_sec_005fauthorize_005f0, PageContext _jspx_page_context)
          throws Throwable {
    PageContext pageContext = _jspx_page_context;
    JspWriter out = _jspx_page_context.getOut();
    //  c:if
    org.apache.taglibs.standard.tag.rt.core.IfTag _jspx_th_c_005fif_005f1 = (org.apache.taglibs.standard.tag.rt.core.IfTag) _005fjspx_005ftagPool_005fc_005fif_0026_005ftest.get(org.apache.taglibs.standard.tag.rt.core.IfTag.class);
    _jspx_th_c_005fif_005f1.setPageContext(_jspx_page_context);
    _jspx_th_c_005fif_005f1.setParent((javax.servlet.jsp.tagext.Tag) _jspx_th_sec_005fauthorize_005f0);
    // /WEB-INF/pages/dashboard.jsp(427,2) name = test type = boolean reqTime = true required = true fragment = false deferredValue = false deferredMethod = false expectedTypeName = null methodSignature = null 
    _jspx_th_c_005fif_005f1.setTest(((java.lang.Boolean) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${pageContext.request.userPrincipal.name != null}", java.lang.Boolean.class, (PageContext)_jspx_page_context, null, false)).booleanValue());
    int _jspx_eval_c_005fif_005f1 = _jspx_th_c_005fif_005f1.doStartTag();
    if (_jspx_eval_c_005fif_005f1 != javax.servlet.jsp.tagext.Tag.SKIP_BODY) {
      do {
        out.write("\r\n");
        out.write("\t\t\t<h2>\r\n");
        out.write("\t\t\t\tUser : ");
        out.write((java.lang.String) org.apache.jasper.runtime.PageContextImpl.proprietaryEvaluate("${pageContext.request.userPrincipal.name}", java.lang.String.class, (PageContext)_jspx_page_context, null, false));
        out.write(" | <a\r\n");
        out.write("\t\t\t\t\thref=\"javascript:formSubmit()\"> Logout</a>\r\n");
        out.write("\t\t\t</h2>\r\n");
        out.write("\t\t");
        int evalDoAfterBody = _jspx_th_c_005fif_005f1.doAfterBody();
        if (evalDoAfterBody != javax.servlet.jsp.tagext.BodyTag.EVAL_BODY_AGAIN)
          break;
      } while (true);
    }
    if (_jspx_th_c_005fif_005f1.doEndTag() == javax.servlet.jsp.tagext.Tag.SKIP_PAGE) {
      _005fjspx_005ftagPool_005fc_005fif_0026_005ftest.reuse(_jspx_th_c_005fif_005f1);
      return true;
    }
    _005fjspx_005ftagPool_005fc_005fif_0026_005ftest.reuse(_jspx_th_c_005fif_005f1);
    return false;
  }
}
